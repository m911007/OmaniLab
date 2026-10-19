<?php

namespace App\Modules\Climate\Connectors;

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
 * مؤشرات المناخ العالمي الرسمية شهريًا: تركيز ثاني أكسيد الكربون (ماونا لوا) والميثان عالميًا (NOAA GML)، وشذوذ حرارة الأرض من ناسا (GISTEMP)
 * ومن NOAA NCEI، ومؤشر النينيو والنينيا ONI (NOAA CPC). ملفات عامة مفتوحة بلا مفتاح.
 */
final class GlobalClimateConnector extends ApiConnector
{
    private const CO2 = 'https://gml.noaa.gov/webdata/ccgg/trends/co2/co2_mm_mlo.csv';

    private const CH4 = 'https://gml.noaa.gov/webdata/ccgg/trends/ch4/ch4_mm_gl.csv';

    private const GISS = 'https://data.giss.nasa.gov/gistemp/tabledata_v4/GLB.Ts+dSST.csv';

    private const NCEI = 'https://www.ncei.noaa.gov/access/monitoring/climate-at-a-glance/global/time-series/globe/tavg/land_ocean/1/0/1850-2026/data.csv';

    private const ONI = 'https://www.cpc.ncep.noaa.gov/data/indices/oni.ascii.txt';

    private const SEASON_CENTER = ['DJF' => 1, 'JFM' => 2, 'FMA' => 3, 'MAM' => 4, 'AMJ' => 5, 'MJJ' => 6, 'JJA' => 7, 'JAS' => 8, 'ASO' => 9, 'SON' => 10, 'OND' => 11, 'NDJ' => 12];

    public function definition(): DatasetDefinition
    {
        return new DatasetDefinition(
            key: 'climate.global',
            source: new SourceDefinition(key: 'noaa_nasa', nameAr: 'NOAA وناسا (المناخ العالمي)', nameEn: 'NOAA and NASA (global climate)', publisher: 'NOAA / NASA GISS', url: 'https://gml.noaa.gov/ccgg/trends/', license: 'عام (Public domain)'),
            nameAr: 'المناخ العالمي: الكربون والميثان وحرارة الأرض والنينيو',
            nameEn: 'Global climate: CO2, methane, temperature, ENSO',
            section: 'energy',
            intervalMinutes: 24 * 60,
            indicators: [
                new IndicatorDefinition('climate.co2_ppm', 'تركيز ثاني أكسيد الكربون في الغلاف الجوي (ماونا لوا)', 'Atmospheric CO2 (Mauna Loa)', 'جزء في المليون', 'M', [new Range(250, 700, 'reject')]),
                new IndicatorDefinition('climate.ch4_ppb', 'تركيز الميثان العالمي في الغلاف الجوي', 'Global atmospheric methane', 'جزء في المليار', 'M', [new Range(1000, 3000, 'reject')]),
                new IndicatorDefinition('climate.temp_anomaly_giss', 'شذوذ حرارة الأرض (ناسا، قاعدة 1951–1980)', 'Global temperature anomaly (NASA GISTEMP, 1951-80)', '°م', 'M', [new Range(-3, 5, 'reject')]),
                new IndicatorDefinition('climate.temp_anomaly_noaa', 'شذوذ حرارة الأرض والمحيط (NOAA، قاعدة 1901–2000)', 'Global land-ocean anomaly (NOAA, 1901-2000)', '°م', 'M', [new Range(-3, 5, 'reject')]),
                new IndicatorDefinition('climate.oni', 'مؤشر النينيو والنينيا (ONI)', 'Oceanic Niño Index (ONI)', '°م', 'M', [new Range(-5, 5, 'reject')]),
            ],
        );
    }

    public function probe(): ProbeResult
    {
        return new ProbeResult(token: null);
    }

    public function fetch(): RawPayload
    {
        $out = [];
        foreach ([self::CO2 => 'co2', self::CH4 => 'ch4', self::GISS => 'giss', self::NCEI => 'ncei', self::ONI => 'oni'] as $url => $kind) {
            $body = $this->httpAccepting('text/csv')->timeout(120)->retry(3, 4000)->get($url)->throw()->body();
            $rows = match ($kind) {
                'co2' => $this->noaaMonthly($body, 'climate.co2_ppm', 3),
                'ch4' => $this->noaaMonthly($body, 'climate.ch4_ppb', 3),
                'giss' => $this->giss($body),
                'ncei' => $this->ncei($body),
                'oni' => $this->oni($body),
            };
            if ($rows === []) {
                throw new RuntimeException("المناخ العالمي {$kind}: لا بيانات (تغيّر الشكل؟).");
            }
            array_push($out, ...$rows);
        }

        return new RawPayload(json_encode($out, JSON_THROW_ON_ERROR));
    }

    /** @return list<array{0: string, 1: string, 2: float}> */
    private function noaaMonthly(string $body, string $key, int $valueCol): array
    {
        $out = [];
        foreach (preg_split('/\r?\n/', $body) ?: [] as $line) {
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $f = str_getcsv($line, ',', '"', '');
            if (count($f) > $valueCol && preg_match('/^\d{4}$/', $f[0]) && is_numeric($f[1]) && is_numeric($f[$valueCol]) && (float) $f[$valueCol] > 0) {
                $out[] = [$key, sprintf('%s-%02d', $f[0], (int) $f[1]), (float) $f[$valueCol]];
            }
        }

        return $out;
    }

    /** @return list<array{0: string, 1: string, 2: float}> */
    private function giss(string $body): array
    {
        $out = [];
        foreach (preg_split('/\r?\n/', $body) ?: [] as $line) {
            $f = str_getcsv($line, ',', '"', '');
            if (! preg_match('/^\d{4}$/', $f[0] ?? '')) {
                continue;
            }
            for ($m = 1; $m <= 12; $m++) {
                if (isset($f[$m]) && is_numeric($f[$m])) {
                    $out[] = ['climate.temp_anomaly_giss', sprintf('%s-%02d', $f[0], $m), (float) $f[$m]];
                }
            }
        }

        return $out;
    }

    /** @return list<array{0: string, 1: string, 2: float}> */
    private function ncei(string $body): array
    {
        $out = [];
        foreach (preg_split('/\r?\n/', $body) ?: [] as $line) {
            if (preg_match('/^(\d{4})(\d{2}),(-?[\d.]+)/', $line, $m) && (int) $m[2] >= 1 && (int) $m[2] <= 12) {
                $out[] = ['climate.temp_anomaly_noaa', "{$m[1]}-{$m[2]}", (float) $m[3]];
            }
        }

        return $out;
    }

    /** @return list<array{0: string, 1: string, 2: float}> */
    private function oni(string $body): array
    {
        $out = [];
        foreach (preg_split('/\r?\n/', $body) ?: [] as $line) {
            if (preg_match('/^\s*([A-Z]{3})\s+(\d{4})\s+[\d.]+\s+(-?[\d.]+)\s*$/', $line, $m) && isset(self::SEASON_CENTER[$m[1]])) {
                $out[] = ['climate.oni', sprintf('%s-%02d', $m[2], self::SEASON_CENTER[$m[1]]), (float) $m[3]];
            }
        }

        return $out;
    }

    public function parse(RawPayload $payload): iterable
    {
        yield new ParsedEntity('WLD', 'World', 'group', 'العالم');
        foreach (json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR) as [$key, $period, $value]) {
            yield new ParsedRecord($key, 'WLD', $period, $value);
        }
    }
}
