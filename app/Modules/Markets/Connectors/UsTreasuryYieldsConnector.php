<?php

namespace App\Modules\Markets\Connectors;

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
 * منحنى عائد سندات الخزانة الأمريكية اليومي منذ 1990 (وزارة الخزانة الأمريكية) مع العوائد الحقيقية منذ 2003 والفوارق والتضخم المتوقع.
 * الريال العُماني مربوط بالدولار فتنتقل إليه أسعار الفائدة الأمريكية؛ وهي مرجع كلفة الدين السيادي والتمويل.
 */
final class UsTreasuryYieldsConnector extends ApiConnector
{
    private const URL = 'https://home.treasury.gov/resource-center/data-chart-center/interest-rates/daily-treasury-rates.csv/%d/all';

    private const FIRST_YEAR = 1990;

    /** @var array<string, array{0: string, 1: string, 2: string}> عنوان العمود => [مفتاح، عربي، إنجليزي] */
    private const TENORS = [
        '1 Mo' => ['us.yield_1m', 'عائد أذونات الخزانة شهر', 'US Treasury 1-month yield'],
        '3 Mo' => ['us.yield_3m', 'عائد أذونات الخزانة 3 أشهر', 'US Treasury 3-month yield'],
        '6 Mo' => ['us.yield_6m', 'عائد أذونات الخزانة 6 أشهر', 'US Treasury 6-month yield'],
        '1 Yr' => ['us.yield_1y', 'عائد سندات الخزانة سنة', 'US Treasury 1-year yield'],
        '2 Yr' => ['us.yield_2y', 'عائد سندات الخزانة سنتان', 'US Treasury 2-year yield'],
        '3 Yr' => ['us.yield_3y', 'عائد سندات الخزانة 3 سنوات', 'US Treasury 3-year yield'],
        '5 Yr' => ['us.yield_5y', 'عائد سندات الخزانة 5 سنوات', 'US Treasury 5-year yield'],
        '7 Yr' => ['us.yield_7y', 'عائد سندات الخزانة 7 سنوات', 'US Treasury 7-year yield'],
        '10 Yr' => ['us.yield_10y', 'عائد سندات الخزانة 10 سنوات', 'US Treasury 10-year yield'],
        '20 Yr' => ['us.yield_20y', 'عائد سندات الخزانة 20 سنة', 'US Treasury 20-year yield'],
        '30 Yr' => ['us.yield_30y', 'عائد سندات الخزانة 30 سنة', 'US Treasury 30-year yield'],
    ];

    /** @var array<string, array{0: string, 1: string, 2: string}> العوائد الحقيقية (سندات محمية من التضخم TIPS) */
    private const REAL = [
        '5 YR' => ['us.real_yield_5y', 'العائد الحقيقي 5 سنوات (TIPS)', 'US real yield 5-year (TIPS)'],
        '10 YR' => ['us.real_yield_10y', 'العائد الحقيقي 10 سنوات (TIPS)', 'US real yield 10-year (TIPS)'],
        '30 YR' => ['us.real_yield_30y', 'العائد الحقيقي 30 سنة (TIPS)', 'US real yield 30-year (TIPS)'],
    ];

    /** @var array<string, array{0: string, 1: string}> مشتقات: [عربي، إنجليزي] */
    private const DERIVED = [
        'us.spread_10y_2y' => ['فارق العائد 10 سنوات مطروحًا منه سنتان (نقطة مئوية)', '10y minus 2y spread (pp)'],
        'us.spread_10y_3m' => ['فارق العائد 10 سنوات مطروحًا منه 3 أشهر (نقطة مئوية)', '10y minus 3m spread (pp)'],
        'us.breakeven_5y' => ['التضخم المتوقع 5 سنوات (عائد اسمي − حقيقي)', '5y breakeven inflation'],
        'us.breakeven_10y' => ['التضخم المتوقع 10 سنوات (عائد اسمي − حقيقي)', '10y breakeven inflation'],
    ];

    public function definition(): DatasetDefinition
    {
        $indicators = [];
        foreach ([...array_values(self::TENORS), ...array_values(self::REAL)] as [$key, $ar, $en]) {
            $indicators[] = new IndicatorDefinition($key, $ar, $en, '%', 'D', [new Range(-3, 30, 'reject')]);
        }
        foreach (self::DERIVED as $key => [$ar, $en]) {
            $indicators[] = new IndicatorDefinition($key, $ar, $en, '%', 'D', [new Range(-10, 15, 'reject')]);
        }

        return new DatasetDefinition(
            key: 'us.treasury_yields',
            source: new SourceDefinition(
                key: 'us_treasury',
                nameAr: 'وزارة الخزانة الأمريكية',
                nameEn: 'U.S. Department of the Treasury',
                publisher: 'U.S. Treasury',
                url: 'https://home.treasury.gov/policy-issues/financing-the-government/interest-rate-statistics',
                license: 'عام (Public domain)',
            ),
            nameAr: 'عوائد سندات الخزانة الأمريكية (يومي)',
            nameEn: 'US Treasury yield curve (daily)',
            section: 'markets',
            intervalMinutes: 6 * 60,
            indicators: $indicators,
        );
    }

    public function probe(): ProbeResult
    {
        return new ProbeResult(token: null);
    }

    public function fetch(): RawPayload
    {
        $body = [];
        foreach (['nominal' => 'daily_treasury_yield_curve', 'real' => 'daily_treasury_real_yield_curve'] as $kind => $type) {
            foreach (range($kind === 'real' ? 2003 : self::FIRST_YEAR, (int) date('Y')) as $i => $year) {
                if ($i > 0) {
                    usleep(300_000);
                }
                $csv = $this->httpAccepting('text/csv')->timeout(60)->retry(3, 2000)
                    ->get(sprintf(self::URL, $year), ['type' => $type, 'field_tdr_date_value' => $year, '_format' => 'csv'])
                    ->throw()->body();
                if (! str_starts_with($csv, 'Date,')) {
                    throw new RuntimeException("US Treasury {$kind} {$year}: تغيّر شكل الملف.");
                }
                $body[$kind][(string) $year] = $csv;
            }
        }

        return new RawPayload(json_encode($body, JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        yield new ParsedEntity('USA', 'United States', 'country', 'الولايات المتحدة');

        $data = json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR);
        /** @var array<string, array<string, float>> $byDay تاريخ => مفتاح => قيمة، لحساب المشتقات */
        $byDay = [];
        foreach (['nominal' => self::TENORS, 'real' => self::REAL] as $kind => $columns) {
            foreach ($data[$kind] ?? [] as $csv) {
                foreach ($this->rows($csv) as $day => $row) {
                    foreach ($columns as $column => [$key]) {
                        if (isset($row[$column]) && is_numeric($row[$column])) {
                            $byDay[$day][$key] = (float) $row[$column];
                            yield new ParsedRecord($key, 'USA', $day, (float) $row[$column]);
                        }
                    }
                }
            }
        }
        foreach ($byDay as $day => $v) {
            $derived = [
                'us.spread_10y_2y' => isset($v['us.yield_10y'], $v['us.yield_2y']) ? $v['us.yield_10y'] - $v['us.yield_2y'] : null,
                'us.spread_10y_3m' => isset($v['us.yield_10y'], $v['us.yield_3m']) ? $v['us.yield_10y'] - $v['us.yield_3m'] : null,
                'us.breakeven_5y' => isset($v['us.yield_5y'], $v['us.real_yield_5y']) ? $v['us.yield_5y'] - $v['us.real_yield_5y'] : null,
                'us.breakeven_10y' => isset($v['us.yield_10y'], $v['us.real_yield_10y']) ? $v['us.yield_10y'] - $v['us.real_yield_10y'] : null,
            ];
            foreach ($derived as $key => $value) {
                if ($value !== null) {
                    yield new ParsedRecord($key, 'USA', $day, round($value, 4));
                }
            }
        }
    }

    /** @return array<string, array<string, string>> تاريخ ISO => (عنوان العمود => قيمة) */
    private function rows(string $csv): array
    {
        $out = [];
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $csv);
        rewind($handle);
        $header = fgetcsv($handle, 0, ',', '"', '') ?: [];
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if (count($row) !== count($header) || ! preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $row[0], $d)) {
                continue;
            }
            $out["{$d[3]}-{$d[1]}-{$d[2]}"] = array_combine($header, $row);
        }
        fclose($handle);

        return $out;
    }
}
