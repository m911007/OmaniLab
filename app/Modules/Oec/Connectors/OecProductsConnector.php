<?php

namespace App\Modules\Oec\Connectors;

use App\Core\Ingestion\Connectors\ApiConnector;
use App\Core\Ingestion\Data\DatasetDefinition;
use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRelation;
use App\Core\Ingestion\Data\ProbeResult;
use App\Core\Ingestion\Data\RawPayload;
use App\Core\Ingestion\Data\SourceDefinition;
use App\Core\Validation\Rules\Range;
use RuntimeException;

/**
 * صادرات دول الخليج حسب المنتج بتفصيل أربع خانات (HS4، نحو ألف منتج) من قاعدة BACI (CEPII) عبر واجهة OEC العامة:
 * أدق من فصول HS2 لإبراز ما وراء النفط (الأسمدة، البوليمرات، الحديد…). بيانات مرايا مصالَحة بين المصدّر والمستورد،
 * فقد تختلف عن إحصاءات الدولة نفسها. علاقة «الدولة ← المنتج» بالدولار.
 */
final class OecProductsConnector extends ApiConnector
{
    private const URL = 'https://api-v2.oec.world/tesseract/data.jsonrecords';

    private const YEARS = 3;

    /** @var array<string, string> ISO3 => رمز OEC (قارة + ISO3 بحروف صغيرة) */
    private const EXPORTERS = ['OMN' => 'asomn', 'SAU' => 'assau', 'ARE' => 'asare', 'QAT' => 'asqat', 'KWT' => 'askwt', 'BHR' => 'asbhr'];

    public function queue(): string
    {
        return 'ingest-heavy';
    }

    public function definition(): DatasetDefinition
    {
        return new DatasetDefinition(
            key: 'oec.products',
            source: new SourceDefinition(
                key: 'oec',
                nameAr: 'مرصد التعقيد الاقتصادي (OEC)',
                nameEn: 'Observatory of Economic Complexity',
                publisher: 'OEC / CEPII BACI',
                url: 'https://oec.world',
                license: 'OEC terms — attribution required',
                reliability: 'secondary',
            ),
            nameAr: 'الصادرات حسب المنتج (HS4) لدول الخليج',
            nameEn: 'Exports by product (HS4), GCC',
            section: 'trade',
            intervalMinutes: 14 * 24 * 60,
            indicators: [new IndicatorDefinition('oec.hs4_exports', 'الصادرات حسب المنتج (HS4)', 'Exports by HS4 product', 'دولار', 'A', [new Range(0, 5e11, 'reject')])],
        );
    }

    public function probe(): ProbeResult
    {
        return new ProbeResult(token: null);
    }

    public function fetch(): RawPayload
    {
        $rows = [];
        foreach (self::EXPORTERS as $iso => $id) {
            $found = 0;
            for ($year = (int) date('Y') - 1; $year >= (int) date('Y') - 5 && $found < self::YEARS; $year--) {
                usleep((int) config('omanilab.ingest.oec_throttle_microseconds', 1_000_000));
                $res = $this->http()->timeout(90)->get(self::URL, [
                    'cube' => 'trade_i_baci_a_92', 'drilldowns' => 'HS4,Year', 'measures' => 'Trade Value', 'locale' => 'en',
                    'Exporter Country' => $id, 'Year' => $year, 'limit' => 3000,
                ])->throw()->json();
                if (! isset($res['data']) || ! is_array($res['data'])) {
                    throw new RuntimeException("OEC {$iso}/{$year}: استجابة غير متوقعة.");
                }
                $any = false;
                foreach ($res['data'] as $r) {
                    if (isset($r['HS4 ID'], $r['Trade Value'], $r['HS4']) && $r['Trade Value'] > 0) {
                        $rows[] = [$iso, substr((string) $r['HS4 ID'], -4), (string) $r['HS4'], (string) $year, (float) $r['Trade Value']];
                        $any = true;
                    }
                }
                $found += $any ? 1 : 0;
            }
        }
        if ($rows === []) {
            throw new RuntimeException('OEC: لا بيانات لأي دولة.');
        }

        return new RawPayload(json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        $rows = json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR);
        $seen = [];
        foreach ($rows as [, $code, $name]) {
            if (! isset($seen[$code])) {
                $seen[$code] = true;
                yield new ParsedEntity("HS{$code}", mb_substr($name, 0, 200), 'other');
            }
        }
        foreach ($rows as [$iso, $code, , $year, $value]) {
            yield new ParsedRelation('oec.hs4_exports', $iso, "HS{$code}", $year, (float) $value);
        }
    }
}
