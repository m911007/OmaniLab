<?php

namespace App\Modules\Oecd\Connectors;

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
 * مؤشرات منظمة التعاون والتنمية (OECD) الاستشرافية شهريًا: المؤشر المركب الرائد (CLI) الذي يسبق دورة النشاط الاقتصادي بنحو 6–9 أشهر،
 * وثقة الأعمال وثقة المستهلك المركبتان، لاقتصادات G20 الكبرى ومجموعاتها. قيمة 100 = المتوسط طويل الأجل.
 */
final class OecdLeadingIndicatorsConnector extends ApiConnector
{
    private const URL = 'https://sdmx.oecd.org/public/rest/data/OECD.SDD.STES,DSD_STES@DF_CLI/all';

    /** @var array<string, array{0: string, 1: string, 2: string, 3: string}> مفتاح => [MEASURE, عربي، إنجليزي، تعديل] */
    private const SERIES = [
        'oecd.cli' => ['LI', 'المؤشر المركب الرائد (CLI)', 'OECD composite leading indicator', 'AA'],
        'oecd.business_confidence' => ['BCICP', 'ثقة الأعمال المركبة (OECD)', 'OECD composite business confidence', 'AA'],
        'oecd.consumer_confidence' => ['CCICP', 'ثقة المستهلك المركبة (OECD)', 'OECD composite consumer confidence', 'AA'],
    ];

    private const GROUPS = ['NAFTA', 'G4E', 'G7', 'G20', 'A5M', 'OECD', 'EA19', 'EA20', 'EU27_2020', 'OECDE', 'OECDAM'];

    public function queue(): string
    {
        return 'ingest-heavy';
    }

    public function definition(): DatasetDefinition
    {
        $indicators = [];
        foreach (self::SERIES as $key => [, $ar, $en]) {
            $indicators[] = new IndicatorDefinition($key, $ar, $en, 'مؤشر (100 = المتوسط)', 'M', [new Range(50, 150, 'reject')]);
        }

        return new DatasetDefinition(
            key: 'oecd.leading_indicators',
            source: new SourceDefinition(key: 'oecd', nameAr: 'منظمة التعاون الاقتصادي والتنمية', nameEn: 'OECD', publisher: 'OECD', url: 'https://data-explorer.oecd.org', license: 'OECD Terms and Conditions'),
            nameAr: 'مؤشرات OECD الاستشرافية: الرائد وثقة الأعمال والمستهلك',
            nameEn: 'OECD leading indicators: CLI, business and consumer confidence',
            section: 'markets',
            intervalMinutes: 24 * 60,
            indicators: $indicators,
        );
    }

    public function probe(): ProbeResult
    {
        return new ProbeResult(token: null);
    }

    public function fetch(): RawPayload
    {
        $path = tempnam(sys_get_temp_dir(), 'oecd');
        $out = [];
        $names = [];
        try {
            $this->httpAccepting('text/csv')->timeout(300)->retry(3, 5000)->sink($path)->get(self::URL, ['startPeriod' => '1970-01', 'format' => 'csvfilewithlabels'])->throw();
            $handle = fopen($path, 'r');
            $header = $handle ? fgetcsv($handle, 0, ',', '"', '') : false;
            $col = $header ? array_flip($header) : [];
            foreach (['REF_AREA', 'Reference area', 'FREQ', 'MEASURE', 'ADJUSTMENT', 'TRANSFORMATION', 'TIME_PERIOD', 'OBS_VALUE'] as $need) {
                if (! isset($col[$need])) {
                    throw new RuntimeException("OECD: تغيّر شكل الملف (العمود {$need}).");
                }
            }
            $wanted = [];
            foreach (self::SERIES as $key => [$measure, , , $adj]) {
                $wanted["{$measure}|{$adj}"] = $key;
            }
            while (($f = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $key = $wanted["{$f[$col['MEASURE']]}|{$f[$col['ADJUSTMENT']]}"] ?? null;
                if ($key === null || $f[$col['FREQ']] !== 'M' || $f[$col['TRANSFORMATION']] !== 'IX' || ! is_numeric($f[$col['OBS_VALUE']]) || ! preg_match('/^\d{4}-\d{2}$/', $f[$col['TIME_PERIOD']])) {
                    continue;
                }
                $out[] = [$key, $f[$col['REF_AREA']], $f[$col['TIME_PERIOD']], (float) $f[$col['OBS_VALUE']]];
                $names[$f[$col['REF_AREA']]] = $f[$col['Reference area']];
            }
            fclose($handle);
        } finally {
            @unlink($path);
        }
        if ($out === []) {
            throw new RuntimeException('OECD: لا بيانات.');
        }

        return new RawPayload(json_encode(['names' => $names, 'rows' => $out], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        $data = json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR);
        foreach ($data['names'] as $code => $name) {
            $isGroup = in_array($code, self::GROUPS, true) || ! preg_match('/^[A-Z]{3}$/', $code);
            yield new ParsedEntity($code, $name, $isGroup ? 'group' : 'country');
        }
        foreach ($data['rows'] as [$key, $code, $period, $value]) {
            yield new ParsedRecord($key, $code, $period, $value);
        }
    }
}
