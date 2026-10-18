<?php

namespace App\Modules\Adp\Connectors;

use App\Core\Ingestion\Connectors\ApiConnector;
use App\Core\Ingestion\Data\DatasetDefinition;
use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\ProbeResult;
use App\Core\Ingestion\Data\RawPayload;
use App\Core\Ingestion\Data\SourceDefinition;
use App\Core\Validation\Rules\Range;
use App\Modules\Adp\AdpCatalog;
use RuntimeException;

/** أساس مجموعات بوابة التنمية العربية: المواصفات في config/adp.php وهوية السلاسل في اللقطة (adp:snapshot). */
abstract class AdpConnector extends ApiConnector
{
    abstract protected function datasetId(): string;

    /** @return array<string, mixed> */
    private function spec(): array
    {
        return (array) config('adp.datasets.'.$this->datasetId());
    }

    public function queue(): string
    {
        return 'ingest-heavy';
    }

    public function definition(): DatasetDefinition
    {
        $s = $this->spec();
        $indicators = [];
        foreach (AdpCatalog::snapshot($this->datasetId()) as $id => $series) {
            $indicators[] = new IndicatorDefinition($id, $series['name'], $series['name'], $series['unit'], 'A', [new Range(-1e15, 1e15, 'reject')]);
        }

        return new DatasetDefinition(
            key: 'adp.'.$this->datasetId(),
            source: new SourceDefinition(
                key: 'adp',
                nameAr: 'بوابة التنمية العربية (الأسكوا)',
                nameEn: 'Arab Development Portal (ESCWA)',
                publisher: 'UN ESCWA',
                url: 'https://data.arabdevelopmentportal.org',
                license: 'شروط البوابة',
                reliability: 'secondary',
            ),
            nameAr: (string) $s['name_ar'],
            nameEn: (string) $s['name_en'],
            section: (string) $s['section'],
            intervalMinutes: 7 * 24 * 60,
            indicators: $indicators,
            reliability: 'secondary',
        );
    }

    public function probe(): ProbeResult
    {
        return new ProbeResult(token: null);
    }

    /** @return list<array<string, mixed>> */
    public function download(): array
    {
        $s = $this->spec();
        $res = $this->httpAccepting('application/json')->timeout(290)->retry(3, 20_000)->get((string) config('adp.api'), [
            'name' => $s['uuid'], 'organization' => $s['organization'], 'source' => $s['source'], 'ReturnFull' => 'true',
        ])->throw()->json();
        $rows = $res['data']['data'] ?? null;
        if (! is_array($rows)) {
            throw new RuntimeException('ADP '.$this->datasetId().': تغيّر شكل الاستجابة.');
        }

        return $rows;
    }

    public function fetch(): RawPayload
    {
        $catalog = new AdpCatalog($this->spec());
        // قيمتان مختلفتان لنفس (دولة، سلسلة، سنة) تعني تفصيلًا غير ممثَّل في الهوية: تُستبعد النقطة بدل تخمين الصحيحة.
        $seen = [];
        $conflicts = [];
        foreach ($catalog->observations($this->download()) as $o) {
            $key = $o[0].'|'.$o[1].'|'.$o[3];
            if (isset($seen[$key]) && $seen[$key][4] !== $o[4]) {
                $conflicts[$key] = true;
            }
            $seen[$key] ??= $o;
        }
        $out = array_values(array_diff_key($seen, $conflicts));

        return new RawPayload(json_encode($out, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        $catalog = new AdpCatalog($this->spec());
        $known = AdpCatalog::snapshot($this->datasetId());
        foreach (json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR) as [$iso, $name, $unit, $year, $value]) {
            $id = $catalog->seriesId($this->datasetId(), $name, $unit);
            if (isset($known[$id])) {
                yield new ParsedRecord($id, $iso, $year, $value);
            }
        }
    }
}
