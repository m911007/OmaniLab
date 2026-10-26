<?php

namespace App\Modules\Comtrade\Connectors;

use App\Core\Ingestion\Connectors\ApiConnector;
use App\Core\Ingestion\Data\DatasetDefinition;
use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRelation;
use App\Core\Ingestion\Data\ProbeResult;
use App\Core\Ingestion\Data\RawPayload;
use App\Core\Ingestion\Data\SourceDefinition;
use App\Core\Support\ApiKeys;
use App\Core\Validation\Rules\Range;
use App\Modules\Comtrade\HsChapters;
use RuntimeException;

/**
 * تركيبة الصادرات والواردات حسب المنتج (فصول النظام المنسّق HS، خانتان) لعُمان ودول الخليج، من واجهة UN Comtrade العامة بلا مفتاح.
 * علاقة موجَّهة «الدولة ← الفصل» (HS27 = الوقود المعدني…) فتعمل مع واجهة /relations الموجودة. الواجهة العامة تقطع عند 500 صف،
 * وفصول HS2 نحو 97 صفًا فتكفي. القيم بالدولار الأمريكي؛ السنوات الأحدث التي فيها بيانات (ثلاث بحد أقصى).
 */
final class ComtradeProductsConnector extends ApiConnector
{
    private const URL = 'https://comtradeapi.un.org/public/v1/preview/C/A/HS';

    private const YEARS = 3;

    /** @var array<string, int> ISO3 => رمز UN M49 */
    private const REPORTERS = ['OMN' => 512, 'SAU' => 682, 'ARE' => 784, 'QAT' => 634, 'KWT' => 414, 'BHR' => 48];

    private const FLOWS = ['X' => 'comtrade.hs_exports', 'M' => 'comtrade.hs_imports'];

    public function queue(): string
    {
        return 'ingest-heavy';
    }

    public function definition(): DatasetDefinition
    {
        $rules = [new Range(0, 5e11, 'reject')];

        return new DatasetDefinition(
            key: 'comtrade.products',
            source: new SourceDefinition(
                key: 'un_comtrade',
                nameAr: 'قاعدة الأمم المتحدة للتجارة (UN Comtrade)',
                nameEn: 'UN Comtrade',
                publisher: 'United Nations Statistics Division',
                url: 'https://comtradeplus.un.org',
                license: 'UN Comtrade terms of use',
            ),
            nameAr: 'التجارة حسب المنتج (فصول HS) لدول الخليج',
            nameEn: 'Trade by product (HS chapters), GCC',
            section: 'trade',
            intervalMinutes: 7 * 24 * 60,
            indicators: [
                new IndicatorDefinition('comtrade.hs_exports', 'الصادرات حسب المنتج', 'Exports by HS chapter', 'دولار', 'A', $rules),
                new IndicatorDefinition('comtrade.hs_imports', 'الواردات حسب المنتج', 'Imports by HS chapter', 'دولار', 'A', $rules),
            ],
        );
    }

    public function probe(): ProbeResult
    {
        return new ProbeResult(token: null);
    }

    /** @return list<array{value: float, chapter: string}> */
    private function chapters(int $reporter, int $year, string $flow): array
    {
        $res = $this->http()->withHeaders(ApiKeys::comtradeHeaders())->timeout(60)->get(ApiKeys::comtradeUrl(self::URL), [
            'reporterCode' => $reporter, 'period' => $year, 'cmdCode' => 'AG2', 'flowCode' => $flow,
            'partnerCode' => 0, 'partner2Code' => 0, 'customsCode' => 'C00', 'motCode' => 0,
        ])->throw()->json();
        if (! isset($res['data']) || ! is_array($res['data'])) {
            throw new RuntimeException("Comtrade {$reporter}/{$year}/{$flow}: استجابة غير متوقعة.");
        }

        $out = [];
        foreach ($res['data'] as $r) {
            if (isset($r['cmdCode'], $r['primaryValue']) && preg_match('/^\d{2}$/', (string) $r['cmdCode']) && is_numeric($r['primaryValue']) && $r['primaryValue'] > 0) {
                $out[] = ['chapter' => (string) $r['cmdCode'], 'value' => (float) $r['primaryValue']];
            }
        }

        return $out;
    }

    public function fetch(): RawPayload
    {
        $rows = [];
        foreach (self::REPORTERS as $iso => $code) {
            $found = 0;
            for ($year = (int) date('Y') - 1; $year >= (int) date('Y') - 6 && $found < self::YEARS; $year--) {
                $any = false;
                foreach (self::FLOWS as $flow => $key) {
                    usleep((int) config('omanilab.ingest.comtrade_throttle_microseconds', 1_200_000));
                    foreach ($this->chapters($code, $year, $flow) as $c) {
                        $rows[] = [$key, $iso, 'HS'.$c['chapter'], (string) $year, $c['value']];
                        $any = true;
                    }
                }
                $found += $any ? 1 : 0;
            }
        }
        if ($rows === []) {
            throw new RuntimeException('Comtrade: لا بيانات لأي دولة.');
        }

        return new RawPayload(json_encode($rows, JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        $rows = json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR);
        $seen = [];
        foreach ($rows as [, , $hs]) {
            if (! isset($seen[$hs])) {
                $seen[$hs] = true;
                $chapter = substr($hs, 2);
                yield new ParsedEntity($hs, 'HS '.$chapter, 'other', HsChapters::AR[$chapter] ?? null);
            }
        }
        foreach ($rows as [$key, $iso, $hs, $year, $value]) {
            yield new ParsedRelation($key, $iso, $hs, $year, (float) $value);
        }
    }
}
