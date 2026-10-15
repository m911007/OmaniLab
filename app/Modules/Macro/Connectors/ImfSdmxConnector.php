<?php

namespace App\Modules\Macro\Connectors;

use App\Core\Ingestion\Connectors\ApiConnector;
use App\Core\Ingestion\Data\DatasetDefinition;
use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\ProbeResult;
use App\Core\Ingestion\Data\RawPayload;
use App\Core\Ingestion\Data\SourceDefinition;
use RuntimeException;

/**
 * أساس مجموعات صندوق النقد على واجهة SDMX 2.1 الجديدة (api.imf.org) بصيغة CSV: مفتاح الاستعلام يفلتر الدول والمؤشرات،
 * والاستجابة تُختزل إلى (دولة، فترة، قيمة، رمز المؤشر) قبل التخزين الخام. الدول ثلاثية الحروف فقط (المجموعات تُتخطى).
 */
abstract class ImfSdmxConnector extends ApiConnector
{
    private const BASE = 'https://api.imf.org/external/sdmx/2.1/data/';

    /** اسم مجموعة SDMX (مثل NDGAIN). */
    abstract protected function flow(): string;

    /** مفتاح الأبعاد المفلتر، مثل ".NDGAIN_IX+RS_NUM.A". */
    abstract protected function key(): string;

    /**
     * أعمدة الاستجابة التي تحمل رمز المؤشر (تُدمج بـ "." إن تعدّدت).
     *
     * @return list<string>
     */
    abstract protected function codeColumns(): array;

    /** @return array<string, IndicatorDefinition> رمز المؤشر في المصدر => التعريف */
    abstract protected function indicators(): array;

    abstract protected function datasetKey(): string;

    abstract protected function datasetNameAr(): string;

    abstract protected function datasetNameEn(): string;

    abstract protected function section(): string;

    protected function intervalMinutes(): int
    {
        return 7 * 24 * 60;
    }

    /** أحدث سنة مسموحة (تُستثنى التوقعات البعيدة). null = بلا حد. */
    protected function lastYear(): ?int
    {
        return null;
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
                url: 'https://data.imf.org',
                license: 'IMF Data Terms',
            ),
            nameAr: $this->datasetNameAr(),
            nameEn: $this->datasetNameEn(),
            section: $this->section(),
            intervalMinutes: $this->intervalMinutes(),
            indicators: array_values($this->indicators()),
        );
    }

    public function probe(): ProbeResult
    {
        return new ProbeResult(token: null);
    }

    public function fetch(): RawPayload
    {
        $csv = $this->httpAccepting('application/vnd.sdmx.data+csv')->timeout(180)
            ->get(self::BASE.$this->flow().'/'.$this->key())->throw()->body();
        if (! str_starts_with($csv, 'DATAFLOW,')) {
            throw new RuntimeException('IMF '.$this->flow().': تغيّر شكل الاستجابة.');
        }

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $csv);
        rewind($handle);
        $header = fgetcsv($handle, 0, ',', '"', '') ?: [];
        $country = array_search('COUNTRY', $header, true);
        $period = array_search('TIME_PERIOD', $header, true);
        $value = array_search('OBS_VALUE', $header, true);
        $codeIdx = array_map(fn (string $c) => array_search($c, $header, true), $this->codeColumns());
        if ($country === false || $period === false || $value === false || in_array(false, $codeIdx, true)) {
            throw new RuntimeException('IMF '.$this->flow().': أعمدة SDMX المتوقعة غير موجودة.');
        }

        $out = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if (count($row) === count($header) && $row[$period] !== '' && $row[$value] !== '') {
                $out[] = [$row[$country], implode('.', array_map(fn (int $i) => $row[$i], $codeIdx)), $row[$period], $row[$value]];
            }
        }
        fclose($handle);

        return new RawPayload(json_encode($out, JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        $indicators = $this->indicators();
        $last = $this->lastYear();
        foreach (json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR) as [$iso, $code, $period, $value]) {
            if (! isset($indicators[$code]) || ! preg_match('/^[A-Z]{3}$/', $iso) || ! preg_match('/^\d{4}$/', $period) || ! is_numeric($value)) {
                continue;
            }
            if ($last !== null && (int) $period > $last) {
                continue;
            }
            yield new ParsedRecord($indicators[$code]->key, $iso, $period, (float) $value);
        }
    }
}
