<?php

namespace App\Modules\Ncsi\Connectors;

use App\Core\Ingestion\Connectors\ApiConnector;
use App\Core\Ingestion\Data\DatasetDefinition;
use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\ProbeResult;
use App\Core\Ingestion\Data\RawPayload;
use App\Core\Ingestion\Data\SourceDefinition;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * بوابة البيانات المفتوحة لسلطنة عُمان (data.gov.om) المبنية على Knoema — المركز الوطني للإحصاء والمعلومات (NCSI).
 * الفحص: meta/dataset/{id} يعيد lastDataChangedOn، فنعرف تغيّر المجموعة دون تنزيل بياناتها.
 * البيانات: POST data/pivot لسلسلة واحدة في كل طلب (كل الأبعاد الجانبية بعضو واحد).
 * يعيد pivot ترددات مختلطة في الرد نفسه، لذا تُصفّى الصفوف بحقل Frequency المطلوب.
 * المفتاح (client_id) هو المفتاح العام المضمَّن في موقع البوابة نفسه.
 */
abstract class KnoemaConnector extends ApiConnector
{
    private const BASE = 'https://data.gov.om/api/1.0';

    private const PUBLIC_CLIENT_ID = 'EZj54KGFo3rzIvnLczrElvAitEyU28DGw9R73tif';

    /** @var array<string, list<array{id: string, members: list<array<string, mixed>>}>> */
    private array $dimensionCache = [];

    abstract protected function knoemaDatasetId(): string;

    /** @return list<KnoemaSeries> */
    abstract protected function series(): array;

    abstract protected function datasetKey(): string;

    abstract protected function datasetNameAr(): string;

    abstract protected function datasetNameEn(): string;

    abstract protected function section(): string;

    protected function intervalMinutes(): int
    {
        return 24 * 60;
    }

    /** المجموعات الواسعة (زراعة، صناعة…) قد تخلو بعض تركيباتها من بيانات؛ تُتخطى بدل إفشال الجلب. */
    protected function skipEmptySeries(): bool
    {
        return false;
    }

    public function definition(): DatasetDefinition
    {
        return new DatasetDefinition(
            key: $this->datasetKey(),
            source: new SourceDefinition(
                key: 'ncsi',
                nameAr: 'المركز الوطني للإحصاء والمعلومات',
                nameEn: 'National Centre for Statistics and Information (NCSI), Oman',
                publisher: 'NCSI',
                url: 'https://data.gov.om',
                license: 'Oman Open Data — attribution required',
            ),
            nameAr: $this->datasetNameAr(),
            nameEn: $this->datasetNameEn(),
            section: $this->section(),
            intervalMinutes: $this->intervalMinutes(),
            indicators: array_map(fn (KnoemaSeries $s) => new IndicatorDefinition(
                key: 'ncsi.'.$s->code, nameAr: $s->nameAr, nameEn: $s->nameEn, unit: $s->unit, frequency: $s->frequency, rules: $s->rules,
            ), $this->series()),
        );
    }

    public function probe(): ProbeResult
    {
        $meta = $this->getJson($this->url('meta/dataset/'.$this->knoemaDatasetId()));
        $changed = $meta['lastDataChangedOn'] ?? null;
        if ($changed === null) {
            throw new RuntimeException('استجابة NCSI بلا lastDataChangedOn للمجموعة '.$this->knoemaDatasetId());
        }

        return new ProbeResult(
            token: (string) $changed,
            version: 'NCSI '.$this->knoemaDatasetId().' — '.substr((string) $changed, 0, 10),
            publishedAt: CarbonImmutable::parse($changed, 'UTC'),
        );
    }

    public function fetch(): RawPayload
    {
        $body = ['series' => []];
        foreach ($this->series() as $i => $series) {
            if ($i > 0) {
                usleep((int) config('omanilab.ingest.knoema_throttle_microseconds'));
            }
            $points = $this->fetchSeries($series);
            if ($points !== []) {
                $body['series'][$series->code] = $points;
            }
        }

        return new RawPayload(json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        $data = json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR);

        yield new ParsedEntity('OMN', 'Oman', 'country', 'سلطنة عُمان');

        foreach ($data['series'] as $code => $points) {
            foreach ($points as [$period, $value]) {
                yield new ParsedRecord("ncsi.{$code}", 'OMN', $period, (float) $value);
            }
        }
    }

    /** @return list<array{0: string, 1: float}> */
    private function fetchSeries(KnoemaSeries $series): array
    {
        $ds = $this->knoemaDatasetId();
        $indicatorDim = null;
        $filters = [];
        foreach ($this->dimensions() as $dim) {
            if (str_starts_with($dim['id'], 'indicator')) {
                $indicatorDim = $dim['id'];

                continue;
            }
            $filters[$dim['id']] = $series->filters[$dim['id']] ?? $this->defaultMember($dim['members']);
        }
        if ($indicatorDim === null) {
            throw new RuntimeException("المجموعة {$ds} بلا بُعد مؤشرات.");
        }

        $body = [
            'Header' => [['DimensionId' => 'Time', 'Members' => [], 'DatasetId' => $ds, 'UiMode' => 'all']],
            'Stub' => [['DimensionId' => $indicatorDim, 'Members' => [$series->indicatorMember], 'DatasetId' => $ds]],
            'Filter' => array_map(fn ($dimId, $member) => ['DimensionId' => $dimId, 'Members' => [$member], 'DatasetId' => $ds], array_keys($filters), array_values($filters)),
            'RegionIdsRequired' => false, 'RegionDimensionId' => null, 'Dataset' => $ds,
            'Frequencies' => [$series->frequency], 'TimeNormalization' => 'none', 'TimeseriesAttributes' => [],
        ];

        $rows = $this->http()->post($this->url('data/pivot'), $body)->throw()->json('data') ?? [];

        $points = [];
        foreach ($rows as $row) {
            if (($row['Frequency'] ?? null) !== $series->frequency || ! isset($row['Value']) || ! is_numeric($row['Value'])) {
                continue;
            }
            $points[$this->period((string) $row['Time'], $series->frequency)] = (float) $row['Value'];
        }

        if ($points === [] && $this->skipEmptySeries()) {
            logger()->warning("[ncsi] السلسلة {$series->code} بلا نقاط — تُتخطى.");

            return [];
        }
        if ($points === []) {
            throw new RuntimeException("السلسلة {$series->code} لم تُرجع نقاطًا (تغيّر الأبعاد أو الأعضاء؟).");
        }

        ksort($points);

        return array_map(fn ($p, $v) => [(string) $p, $v], array_keys($points), array_values($points));
    }

    private function period(string $isoTime, string $frequency): string
    {
        $t = CarbonImmutable::parse($isoTime, 'UTC');

        return match ($frequency) {
            'A' => $t->format('Y'),
            'Q' => $t->format('Y').'-Q'.$t->quarter,
            default => $t->format('Y-m'),
        };
    }

    /** @param  list<array<string, mixed>>  $members */
    private function defaultMember(array $members): int
    {
        foreach ($members as $m) {
            if (! empty($m['hasData'])) {
                return (int) $m['key'];
            }
        }

        return (int) $members[0]['key'];
    }

    /** @return list<array{id: string, members: list<array<string, mixed>>}> */
    private function dimensions(): array
    {
        $ds = $this->knoemaDatasetId();
        if (! isset($this->dimensionCache[$ds])) {
            $meta = $this->getJson($this->url("meta/dataset/{$ds}"));
            $this->dimensionCache[$ds] = [];
            foreach ($meta['dimensions'] ?? [] as $d) {
                $members = $this->getJson($this->url("meta/dataset/{$ds}/dimension/{$d['id']}"))['items'] ?? [];
                $this->dimensionCache[$ds][] = ['id' => (string) $d['id'], 'members' => $members];
            }
        }

        return $this->dimensionCache[$ds];
    }

    private function url(string $path): string
    {
        return self::BASE.'/'.$path.'?client_id='.self::PUBLIC_CLIENT_ID;
    }
}
