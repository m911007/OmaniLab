<?php

namespace App\Modules\Owid\Connectors;

use App\Core\Ingestion\Connectors\ApiConnector;
use App\Core\Ingestion\Data\DatasetDefinition;
use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\ProbeResult;
use App\Core\Ingestion\Data\RawPayload;
use App\Core\Ingestion\Data\SourceDefinition;
use App\Core\Validation\Rules\Range;
use RuntimeException;

/**
 * أساس موصلات Our World in Data: ملف CSV عام لكل مخطط (entity, code, year, value)، الدول ذات رمز ISO3 فقط (المجموعات الإقليمية تُتخطى).
 * OWID مجمِّع للمصادر الأصلية (Ember، Global Carbon Project، Transparency International…)، فتُوسَم موثوقيته «ثانوية» ويُذكر الأصل في اسم المجموعة.
 */
abstract class OwidCsvConnector extends ApiConnector
{
    private const BASE = 'https://ourworldindata.org/grapher/';

    /** @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: int|float}> slug => [مفتاح، عربي، إنجليزي، وحدة، حد أعلى] */
    abstract protected function series(): array;

    abstract protected function datasetKey(): string;

    abstract protected function datasetNameAr(): string;

    abstract protected function datasetNameEn(): string;

    abstract protected function section(): string;

    public function definition(): DatasetDefinition
    {
        $indicators = [];
        foreach ($this->series() as [$key, $ar, $en, $unit, $max]) {
            $indicators[] = new IndicatorDefinition($key, $ar, $en, $unit, 'A', [new Range(0, $max, 'reject')]);
        }

        return new DatasetDefinition(
            key: $this->datasetKey(),
            source: new SourceDefinition(
                key: 'owid',
                nameAr: 'Our World in Data (بيانات Ember)',
                nameEn: 'Our World in Data (Ember, Energy Institute)',
                publisher: 'Our World in Data / Ember',
                url: 'https://ourworldindata.org/energy',
                license: 'CC BY 4.0',
                reliability: 'secondary',
            ),
            nameAr: $this->datasetNameAr(),
            nameEn: $this->datasetNameEn(),
            section: $this->section(),
            intervalMinutes: 3 * 24 * 60,
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
        foreach ($this->series() as $slug => [$key]) {
            $csv = $this->httpAccepting('text/csv')->timeout(90)->get(self::BASE.$slug.'.csv', ['csvType' => 'full', 'useColumnShortNames' => 'true'])->throw()->body();
            if (! str_starts_with($csv, 'entity,code,year,')) {
                throw new RuntimeException("OWID {$slug}: تغيّر شكل الملف.");
            }
            $rows = [];
            foreach (array_slice(explode("\n", trim($csv)), 1) as $line) {
                $f = str_getcsv($line, ',', '"', '');
                if (preg_match('/^[A-Z]{3}$/', $f[1] ?? '') && is_numeric($f[3] ?? null)) {
                    $rows[] = [$f[1], $f[2], $f[3]];
                }
            }
            $body[$key] = $rows;
            usleep(300_000);
        }

        return new RawPayload(json_encode($body, JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        foreach (json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR) as $key => $rows) {
            foreach ($rows as [$iso, $year, $value]) {
                if (preg_match('/^\d{4}$/', (string) $year)) {
                    yield new ParsedRecord($key, $iso, (string) $year, (float) $value);
                }
            }
        }
    }
}
