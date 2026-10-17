<?php

namespace App\Modules\Places\Connectors;

use App\Core\Ingestion\Connectors\ApiConnector;
use App\Core\Ingestion\Data\DatasetDefinition;
use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Ingestion\Data\ParsedAttribute;
use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\ProbeResult;
use App\Core\Ingestion\Data\RawPayload;
use App\Core\Ingestion\Data\SourceDefinition;
use App\Core\Validation\Rules\Range;
use App\Modules\Places\GovernorateLocator;
use RuntimeException;

/**
 * مؤسسات ومرافق وموارد عُمان من OpenStreetMap عبر Overpass: دوائر حكومية، مدارس وجامعات، مستشفيات، محطات طاقة وتحلية، سدود، موانئ…
 * كل موقع كيان من نوع place له سمات (فئة، محافظة، إحداثيات، جهة مشغّلة، موقع إلكتروني)، وأعداد كل فئة مؤشرات سنوية لعُمان.
 * المصدر تطوعي (ثانوي): تغطيته تختلف بين الفئات، فتُعرض الأعداد على أنها «المسجّلة في الخريطة المفتوحة» لا إحصاءً رسميًا.
 */
final class OsmPlacesConnector extends ApiConnector
{
    private const URL = 'https://overpass-api.de/api/interpreter';

    private const KEEP_TAGS = ['name', 'name:ar', 'name:en', 'operator', 'operator:type', 'website', 'contact:website', 'wikidata', 'phone', 'capacity', 'start_date', 'plant:source', 'plant:output:electricity', 'generator:source', 'isced:level', 'religion'];

    public function queue(): string
    {
        return 'ingest-heavy';
    }

    public function definition(): DatasetDefinition
    {
        $indicators = [];
        foreach ((array) config('places.categories') as $key => $c) {
            $indicators[] = new IndicatorDefinition("places.count.{$key}", "عدد {$c['ar']} المسجّلة", "Mapped: {$c['en']}", 'موقع', 'A', [new Range(0, 200000, 'reject')]);
        }

        return new DatasetDefinition(
            key: 'osm.places',
            source: new SourceDefinition(
                key: 'osm',
                nameAr: 'خريطة الشارع المفتوحة (OpenStreetMap)',
                nameEn: 'OpenStreetMap',
                publisher: 'OpenStreetMap contributors',
                url: 'https://www.openstreetmap.org',
                license: 'ODbL 1.0',
                reliability: 'secondary',
            ),
            nameAr: 'المؤسسات والمرافق والموارد (OpenStreetMap)',
            nameEn: 'Institutions, facilities and resources (OpenStreetMap)',
            section: 'oman',
            intervalMinutes: 7 * 24 * 60,
            indicators: $indicators,
            reliability: 'secondary',
        );
    }

    public function probe(): ProbeResult
    {
        return new ProbeResult(token: null);
    }

    public function fetch(): RawPayload
    {
        $out = [];
        foreach ((array) config('places.categories') as $key => $c) {
            $parts = array_map(fn (string $f) => "nwr{$f}(area.a);", $c['filter']);
            $query = '[out:json][timeout:240];area["ISO3166-1"="OM"]->.a;('.implode('', $parts).');out tags center;';
            $res = $this->httpAccepting('application/json')->timeout(270)->retry(8, fn (int $attempt): int => min(120_000, $attempt * 20_000))->asForm()->post(self::URL, ['data' => $query])->throw()->json();
            if (! isset($res['elements']) || ! is_array($res['elements'])) {
                throw new RuntimeException("Overpass {$key}: تغيّر شكل الاستجابة.");
            }
            $rows = [];
            foreach ($res['elements'] as $e) {
                $lat = $e['lat'] ?? $e['center']['lat'] ?? null;
                $lon = $e['lon'] ?? $e['center']['lon'] ?? null;
                if ($lat === null || $lon === null || ! isset($e['type'], $e['id'])) {
                    continue;
                }
                $rows[] = [substr((string) $e['type'], 0, 1), (int) $e['id'], round((float) $lat, 5), round((float) $lon, 5), array_intersect_key((array) ($e['tags'] ?? []), array_flip(self::KEEP_TAGS))];
            }
            $out[$key] = $rows;
            sleep(5);
        }

        return new RawPayload(json_encode(['fetched' => gmdate('Y-m-d\TH:i'), 'categories' => $out], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        $data = json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR);
        $locator = new GovernorateLocator;
        $categories = (array) config('places.categories');
        $year = substr((string) $data['fetched'], 0, 4);
        $seen = [];

        foreach ($data['categories'] as $key => $rows) {
            $meta = $categories[$key] ?? null;
            if ($meta === null) {
                continue;
            }
            $count = 0;
            foreach ($rows as [$type, $id, $lat, $lon, $tags]) {
                $code = "osm:{$type}:{$id}";
                if (isset($seen[$code])) {
                    continue;
                }
                $arabic = $this->arabicName($tags);
                $english = (string) ($tags['name:en'] ?? ($this->hasArabic((string) ($tags['name'] ?? '')) ? '' : ($tags['name'] ?? '')));
                $name = $english !== '' ? $english : ($arabic ?? '');
                if ($name === '' && empty($meta['allow_unnamed'])) {
                    continue;
                }
                $seen[$code] = true;
                $count++;
                yield new ParsedEntity($code, $name !== '' ? mb_substr($name, 0, 250) : $meta['en'].' '.$id, 'place', $arabic !== null ? mb_substr($arabic, 0, 250) : null);

                $attrs = [
                    'category' => $key, 'group' => $meta['group'], 'lat' => $lat, 'lon' => $lon, 'governorate' => $locator->find($lat, $lon),
                    'operator' => $tags['operator'] ?? null, 'sector' => $tags['operator:type'] ?? null,
                    'website' => $this->url($tags['website'] ?? $tags['contact:website'] ?? null), 'wikidata' => $tags['wikidata'] ?? null,
                    'osm_url' => 'https://www.openstreetmap.org/'.['n' => 'node', 'w' => 'way', 'r' => 'relation'][$type].'/'.$id,
                    'source_kind' => $tags['plant:source'] ?? $tags['generator:source'] ?? null, 'seen' => $data['fetched'],
                ];
                foreach ($attrs as $k => $v) {
                    if ($v !== null && $v !== '') {
                        yield new ParsedAttribute($code, $k, $v);
                    }
                }
            }
            yield new ParsedRecord("places.count.{$key}", 'OMN', $year, (float) $count);
        }
    }

    /** @param  array<string, string>  $tags */
    private function arabicName(array $tags): ?string
    {
        foreach ([$tags['name:ar'] ?? null, $tags['name'] ?? null] as $candidate) {
            if (is_string($candidate) && $this->hasArabic($candidate)) {
                return trim($candidate);
            }
        }

        return null;
    }

    private function hasArabic(string $s): bool
    {
        return (bool) preg_match('/\p{Arabic}/u', $s);
    }

    private function url(?string $u): ?string
    {
        if ($u === null || $u === '') {
            return null;
        }
        $u = str_starts_with($u, 'http') ? $u : 'https://'.$u;

        return filter_var($u, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $u) ? mb_substr($u, 0, 300) : null;
    }
}
