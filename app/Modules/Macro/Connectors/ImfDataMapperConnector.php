<?php

namespace App\Modules\Macro\Connectors;

use App\Core\Ingestion\Connectors\ApiConnector;
use App\Core\Ingestion\Data\DatasetDefinition;
use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\ProbeResult;
use App\Core\Ingestion\Data\RawPayload;
use App\Core\Ingestion\Data\SourceDefinition;
use Carbon\CarbonImmutable;

/**
 * أساس مشترك لمجموعات صندوق النقد عبر IMF DataMapper API (WEO، مرصد المالية، قاعدة الدين، الاحتياطيات…).
 * الفحص: GET /indicators يعيد last-modified لكل مؤشر، فنعرف صدور إصدار جديد دون تنزيل البيانات.
 * كل مجموعة تعرّف مؤشراتها؛ الرمز الداخلي imf.<رمز IMF>.
 */
abstract class ImfDataMapperConnector extends ApiConnector
{
    protected const BASE = 'https://www.imf.org/external/datamapper/api/v1';

    /** جدار حماية imf.org يرفض (403) أي User-Agent عدا عملاء الأوامر مثل curl، رغم أن الواجهة عامة ومفتوحة. */
    protected function userAgent(): string
    {
        return 'curl/8.7.1';
    }

    /** @return list<IndicatorDefinition> */
    abstract protected function indicators(): array;

    abstract protected function datasetKey(): string;

    abstract protected function datasetNameAr(): string;

    abstract protected function datasetNameEn(): string;

    protected function section(): string
    {
        return 'macro';
    }

    protected function intervalMinutes(): int
    {
        return 7 * 24 * 60;
    }

    public function definition(): DatasetDefinition
    {
        return new DatasetDefinition(
            key: $this->datasetKey(),
            source: new SourceDefinition(
                key: 'imf',
                nameAr: 'صندوق النقد الدولي',
                nameEn: 'International Monetary Fund',
                publisher: 'IMF',
                url: 'https://www.imf.org/external/datamapper',
                license: 'IMF Terms of Use — attribution required',
            ),
            nameAr: $this->datasetNameAr(),
            nameEn: $this->datasetNameEn(),
            section: $this->section(),
            intervalMinutes: $this->intervalMinutes(),
            indicators: $this->indicators(),
        );
    }

    /** @return list<string> رموز IMF (بدون بادئة imf.) */
    protected function codes(): array
    {
        return array_map(fn (IndicatorDefinition $i) => substr($i->key, 4), $this->indicators());
    }

    public function probe(): ProbeResult
    {
        $meta = $this->getJson(self::BASE.'/indicators')['indicators'] ?? [];

        $parts = [];
        $version = null;
        $latest = null;
        foreach ($this->codes() as $code) {
            $m = $meta[$code] ?? null;
            if ($m === null) {
                throw new \RuntimeException("المؤشر {$code} لم يعد موجودًا في قائمة IMF.");
            }
            $parts[] = $code.':'.($m['last-modified'] ?? '');
            $version ??= $m['source'] ?? null;
            if (! empty($m['last-modified'])) {
                $at = CarbonImmutable::parse($m['last-modified'], 'UTC');
                $latest = ($latest === null || $at->gt($latest)) ? $at : $latest;
            }
        }

        return new ProbeResult(sha1(implode('|', $parts)), $version, $latest);
    }

    public function fetch(): RawPayload
    {
        $body = [
            'countries' => $this->getJson(self::BASE.'/countries')['countries'] ?? [],
            'regions' => $this->getJson(self::BASE.'/regions')['regions'] ?? [],
            'groups' => $this->getJson(self::BASE.'/groups')['groups'] ?? [],
            'values' => [],
        ];

        foreach ($this->codes() as $code) {
            $body['values'][$code] = $this->getJson(self::BASE.'/'.$code)['values'][$code] ?? [];
        }

        return new RawPayload(json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        $data = json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR);

        foreach (['countries' => 'country', 'regions' => 'region', 'groups' => 'group'] as $bucket => $type) {
            foreach ($data[$bucket] as $code => $info) {
                yield new ParsedEntity((string) $code, (string) ($info['label'] ?? $code), $type);
            }
        }

        foreach ($data['values'] as $code => $byEntity) {
            foreach ($byEntity as $entity => $years) {
                foreach ($years as $year => $value) {
                    if ($value === null || ! is_numeric($value)) {
                        continue;
                    }
                    yield new ParsedRecord("imf.{$code}", (string) $entity, (string) $year, (float) $value);
                }
            }
        }
    }
}
