<?php

namespace App\Modules\GlobalRates\Connectors;

use App\Core\Ingestion\Connectors\ApiConnector;
use App\Core\Ingestion\Data\DatasetDefinition;
use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\ProbeResult;
use App\Core\Ingestion\Data\RawPayload;
use App\Core\Ingestion\Data\SourceDefinition;
use App\Core\Validation\Rules\Range;
use RuntimeException;

/**
 * عوائد السندات الحكومية اليابانية (JGB) اليومية منذ 1974 من وزارة المالية اليابانية (ملف CSV العام للعوائد الثابتة الاستحقاق):
 * 1 و2 و5 و10 و20 و30 سنة. اليابان أكبر دائن في العالم وسياستها النقدية الفريدة تؤثر في أسواق السندات العالمية.
 */
final class MofJapanJgbConnector extends ApiConnector
{
    /** التاريخ حتى نهاية الشهر الماضي، ثم ملف الشهر الجاري. */
    private const URLS = [
        'https://www.mof.go.jp/english/policy/jgbs/reference/interest_rate/historical/jgbcme_all.csv',
        'https://www.mof.go.jp/english/policy/jgbs/reference/interest_rate/jgbcme.csv',
    ];

    /** @var array<string, array{0: string, 1: string}> عنوان العمود => [مفتاح، عربي] */
    private const TENORS = ['1Y' => ['jp.jgb_1y', 'سنة'], '2Y' => ['jp.jgb_2y', 'سنتين'], '5Y' => ['jp.jgb_5y', '5 سنوات'], '10Y' => ['jp.jgb_10y', '10 سنوات'], '20Y' => ['jp.jgb_20y', '20 سنة'], '30Y' => ['jp.jgb_30y', '30 سنة']];

    public function definition(): DatasetDefinition
    {
        $indicators = [];
        foreach (self::TENORS as $col => [$key, $ar]) {
            $indicators[] = new IndicatorDefinition($key, "عائد السندات الحكومية اليابانية {$ar}", "Japan government bond yield {$col}", '%', 'D', [new Range(-2, 20, 'reject')]);
        }

        return new DatasetDefinition(
            key: 'jp.jgb_yields',
            source: new SourceDefinition(key: 'mof_japan', nameAr: 'وزارة المالية اليابانية', nameEn: 'Ministry of Finance Japan', publisher: 'MOF Japan', url: 'https://www.mof.go.jp/english/policy/jgbs/reference/interest_rate/', license: 'شروط الحكومة اليابانية (مع الإشارة إلى المصدر)'),
            nameAr: 'اليابان: عوائد السندات الحكومية (يومي)',
            nameEn: 'Japan: government bond yields (daily)',
            section: 'markets',
            intervalMinutes: 12 * 60,
            indicators: $indicators,
        );
    }

    public function probe(): ProbeResult
    {
        return new ProbeResult(token: null);
    }

    public function fetch(): RawPayload
    {
        $out = [];
        foreach (self::URLS as $url) {
            $csv = $this->httpAccepting('text/csv')->timeout(120)->retry(3, 4000)->get($url)->throw()->body();
            $lines = preg_split('/\r?\n/', trim($csv)) ?: [];
            $headerAt = null;
            foreach ($lines as $i => $line) {
                if (str_starts_with($line, 'Date,')) {
                    $headerAt = $i;
                    break;
                }
            }
            if ($headerAt === null) {
                throw new RuntimeException('MOF Japan: تغيّر شكل الملف.');
            }
            $header = str_getcsv($lines[$headerAt], ',', '"', '');
            foreach (array_slice($lines, $headerAt + 1) as $line) {
                $f = str_getcsv($line, ',', '"', '');
                if (! preg_match('#^(\d{4})/(\d{1,2})/(\d{1,2})$#', $f[0] ?? '', $d)) {
                    continue;
                }
                $day = sprintf('%s-%02d-%02d', $d[1], $d[2], $d[3]);
                foreach (self::TENORS as $col => [$key]) {
                    $i = array_search($col, $header, true);
                    if ($i !== false && isset($f[$i]) && is_numeric($f[$i])) {
                        $out[] = [$key, $day, (float) $f[$i]];
                    }
                }
            }
        }
        if ($out === []) {
            throw new RuntimeException('MOF Japan: لا بيانات.');
        }

        return new RawPayload(json_encode($out, JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        yield new ParsedEntity('JPN', 'Japan', 'country', 'اليابان');
        foreach (json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR) as [$key, $day, $value]) {
            yield new ParsedRecord($key, 'JPN', $day, $value);
        }
    }
}
