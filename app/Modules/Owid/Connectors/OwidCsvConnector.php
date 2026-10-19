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
                nameAr: 'Our World in Data (مجمِّع بيانات؛ الأصل في اسم المجموعة)',
                nameEn: 'Our World in Data (aggregator; original source named in the dataset)',
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
        $files = [];
        foreach ($this->series() as $slugKey => [$key]) {
            // «slug#N»: العمود N (الافتراضي 3) من الملف نفسه؛ يتيح أخذ التقديرات والإسقاطات من مخطط واحد.
            [$slug, $col] = array_pad(explode('#', $slugKey, 2), 2, '3');
            if (! isset($files[$slug])) {
                $csv = $this->httpAccepting('text/csv')->timeout(90)->get(self::BASE.$slug.'.csv', ['csvType' => 'full', 'useColumnShortNames' => 'true'])->throw()->body();
                if (! str_starts_with($csv, 'entity,code,year,')) {
                    throw new RuntimeException("OWID {$slug}: تغيّر شكل الملف.");
                }
                $files[$slug] = array_map(fn (string $line) => str_getcsv($line, ',', '"', ''), array_slice(explode("\n", trim($csv)), 1));
                usleep(300_000);
            }
            $rows = [];
            foreach ($files[$slug] as $f) {
                if (preg_match('/^[A-Z]{3}$/', $f[1] ?? '') && is_numeric($f[(int) $col] ?? null)) {
                    $rows[] = [$f[1], $f[2], $f[(int) $col]];
                }
            }
            $body[$key] = $rows;
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
