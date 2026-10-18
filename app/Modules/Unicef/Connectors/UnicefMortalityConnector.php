<?php

namespace App\Modules\Unicef\Connectors;

use App\Core\Ingestion\Connectors\ApiConnector;
use App\Core\Ingestion\Data\DatasetDefinition;
use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\ProbeResult;
use App\Core\Ingestion\Data\RawPayload;
use App\Core\Ingestion\Data\SourceDefinition;
use App\Core\Validation\Rules\Range;
use RuntimeException;

/** وفيات الأطفال (تقديرات الأمم المتحدة IGME عبر اليونيسف): حديثو الولادة والرضّع ودون الخامسة والفئة 1–4 سنوات، لكل الدول منذ 1990. */
final class UnicefMortalityConnector extends ApiConnector
{
    private const URL = 'https://sdmx.data.unicef.org/ws/public/sdmxapi/rest/data/UNICEF,CME,1.0/.CME_MRM0+CME_MRY0+CME_MRY0T4+CME_MRY1T4._T._T';

    /** @var array<string, array{0: string, 1: string, 2: string}> */
    private const SERIES = [
        'CME_MRM0' => ['unicef.neonatal_mortality', 'وفيات حديثي الولادة', 'Neonatal mortality rate'],
        'CME_MRY0' => ['unicef.infant_mortality', 'وفيات الرضّع', 'Infant mortality rate'],
        'CME_MRY0T4' => ['unicef.under5_mortality', 'وفيات الأطفال دون الخامسة', 'Under-five mortality rate'],
        'CME_MRY1T4' => ['unicef.child_mortality_1_4', 'وفيات الأطفال من سنة إلى أربع', 'Mortality rate, ages 1–4'],
    ];

    public function definition(): DatasetDefinition
    {
        $indicators = [];
        foreach (self::SERIES as [$key, $ar, $en]) {
            $indicators[] = new IndicatorDefinition($key, $ar, $en, 'لكل 1000', 'A', [new Range(0, 1000, 'reject')]);
        }

        return new DatasetDefinition(
            key: 'unicef.mortality',
            source: new SourceDefinition(key: 'unicef', nameAr: 'اليونيسف (UN IGME)', nameEn: 'UNICEF (UN IGME)', publisher: 'UNICEF', url: 'https://data.unicef.org', license: 'CC BY 3.0 IGO'),
            nameAr: 'وفيات الأطفال (اليونيسف)',
            nameEn: 'Child mortality (UNICEF)',
            section: 'society',
            intervalMinutes: 7 * 24 * 60,
            indicators: $indicators,
        );
    }

    public function probe(): ProbeResult
    {
        return new ProbeResult(token: null);
    }

    public function fetch(): RawPayload
    {
        $csv = $this->httpAccepting('application/vnd.sdmx.data+csv')->timeout(180)->retry(3, 5000)->get(self::URL, ['startPeriod' => '1990'])->throw()->body();
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $csv);
        rewind($handle);
        $header = fgetcsv($handle, 0, ',', '"', '') ?: [];
        [$area, $ind, $time, $value] = [array_search('REF_AREA', $header, true), array_search('INDICATOR', $header, true), array_search('TIME_PERIOD', $header, true), array_search('OBS_VALUE', $header, true)];
        if (in_array(false, [$area, $ind, $time, $value], true)) {
            throw new RuntimeException('UNICEF: تغيّر شكل الملف.');
        }
        $rows = [];
        while (($r = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if (isset(self::SERIES[$r[$ind] ?? '']) && preg_match('/^[A-Z]{3}$/', (string) ($r[$area] ?? '')) && preg_match('/^\d{4}$/', (string) ($r[$time] ?? '')) && is_numeric($r[$value] ?? null)) {
                $rows[] = [self::SERIES[$r[$ind]][0], $r[$area], $r[$time], (float) $r[$value]];
            }
        }
        fclose($handle);

        return new RawPayload(json_encode($rows, JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        foreach (json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR) as [$key, $iso, $year, $value]) {
            yield new ParsedRecord($key, $iso, $year, $value);
        }
    }
}
