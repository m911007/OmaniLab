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
 * منحنى عائد سندات الخزانة الأمريكية اليومي (وزارة الخزانة الأمريكية). الريال العُماني مربوط بالدولار فتنتقل إليه
 * أسعار الفائدة الأمريكية؛ وهي مرجع كلفة الدين السيادي والتمويل. نافذة السنة الماضية والجارية (ثابتة داخل السنة).
 */
final class UsTreasuryYieldsConnector extends ApiConnector
{
    private const URL = 'https://home.treasury.gov/resource-center/data-chart-center/interest-rates/daily-treasury-rates.csv/%d/all';

    /** @var array<string, array{0: string, 1: string, 2: string}> عنوان العمود => [مفتاح، عربي، إنجليزي] */
    private const TENORS = [
        '3 Mo' => ['us.yield_3m', 'عائد أذونات الخزانة 3 أشهر', 'US Treasury 3-month yield'],
        '2 Yr' => ['us.yield_2y', 'عائد سندات الخزانة سنتان', 'US Treasury 2-year yield'],
        '10 Yr' => ['us.yield_10y', 'عائد سندات الخزانة 10 سنوات', 'US Treasury 10-year yield'],
        '30 Yr' => ['us.yield_30y', 'عائد سندات الخزانة 30 سنة', 'US Treasury 30-year yield'],
    ];

    public function definition(): DatasetDefinition
    {
        $indicators = [];
        foreach (self::TENORS as [$key, $ar, $en]) {
            $indicators[] = new IndicatorDefinition($key, $ar, $en, '%', 'D', [new Range(-2, 30, 'reject')]);
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
        foreach ([(int) date('Y') - 1, (int) date('Y')] as $year) {
            $csv = $this->httpAccepting('text/csv')->timeout(60)
                ->get(sprintf(self::URL, $year), ['type' => 'daily_treasury_yield_curve', 'field_tdr_date_value' => $year, '_format' => 'csv'])
                ->throw()->body();
            if (! str_starts_with($csv, 'Date,')) {
                throw new RuntimeException("US Treasury {$year}: تغيّر شكل الملف.");
            }
            $body[(string) $year] = $csv;
        }

        return new RawPayload(json_encode($body, JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        yield new ParsedEntity('USA', 'United States', 'country', 'الولايات المتحدة');

        foreach (json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR) as $csv) {
            $handle = fopen('php://temp', 'r+');
            fwrite($handle, $csv);
            rewind($handle);
            $header = fgetcsv($handle, 0, ',', '"', '') ?: [];
            while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                if (count($row) !== count($header) || ! preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $row[0], $d)) {
                    continue;
                }
                foreach (self::TENORS as $column => [$key]) {
                    $i = array_search($column, $header, true);
                    if ($i !== false && is_numeric($row[$i])) {
                        yield new ParsedRecord($key, 'USA', "{$d[3]}-{$d[1]}-{$d[2]}", (float) $row[$i]);
                    }
                }
            }
            fclose($handle);
        }
    }
}
