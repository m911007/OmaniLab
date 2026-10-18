<?php

namespace App\Modules\Firms\Connectors;

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
use RuntimeException;

/**
 * الشركات العالمية من ويكيداتا: كل شركة لها عدد عاملين أو إيرادات أو إدراج في بورصة أو قيمة سوقية، بتفاصيلها
 * (الدولة، المقر، القطاعات، التأسيس، العاملون، الإيرادات بعملتها، الأصول، القيمة السوقية، البورصة والرمز، الرئيس التنفيذي،
 * الشركة الأم، الموقع، رمز LEI، رابط ويكيبيديا، الشعار).
 *
 * الجلب على مرحلتين: (1) SPARQL لمعرّفات شركات كل دولة (استعلام خفيف)، (2) واجهة wbgetentities للتفاصيل (50 كيانًا للطلب) ثم تسميات الكيانات المرتبطة.
 * كل جولة تعالج شريحة من الدول (1/SLICES) بالتناوب، فتغطي كل الدول خلال أيام دون جولة واحدة طويلة؛ الشركات سمات كيانات فلا تضيع شريحة بمعالجة أخرى.
 * المصدر تطوعي (ثانوي): الأرقام كما أدخلها المحرّرون وبعملات مختلفة، فتُعرض بعملتها ولا تُجمع.
 */
final class WikidataFirmsConnector extends ApiConnector
{
    private const SPARQL = 'https://query.wikidata.org/sparql';

    private const API = 'https://www.wikidata.org/w/api.php';

    /** عدد الشرائح؛ والجولة كل ساعة، فتكتمل الدورة خلال يومين (شرائح صغيرة كي لا تتجاوز الجولة مهلة العامل). */
    public const SLICES = 48;

    /** أنواع الكيان المقبولة (instance of). */
    private const CLASSES = ['Q4830453', 'Q783794', 'Q891723', 'Q6881511', 'Q167037', 'Q778575', 'Q161726', 'Q270791', 'Q22687', 'Q46970', 'Q786820', 'Q1058914', 'Q1616075', 'Q3895768', 'Q43229'];

    /** سقف الشركات لكل دولة في الجولة (الدول الكبرى فيها عشرات الآلاف). */
    private const PER_COUNTRY = 1500;

    /** ميزانية زمنية للجولة بالثواني: بعدها تتوقف المعالجة وتُحفظ ما جُمع (مهلة العامل 40 دقيقة). */
    private const BUDGET = 1500;

    public function queue(): string
    {
        return 'ingest-heavy';
    }

    public function definition(): DatasetDefinition
    {
        return new DatasetDefinition(
            key: 'wikidata.firms',
            source: new SourceDefinition(
                key: 'wikidata',
                nameAr: 'ويكيداتا (Wikidata)',
                nameEn: 'Wikidata',
                publisher: 'Wikimedia Foundation',
                url: 'https://www.wikidata.org',
                license: 'CC0 1.0',
                reliability: 'secondary',
            ),
            nameAr: 'الشركات العالمية (ويكيداتا)',
            nameEn: 'Global companies (Wikidata)',
            section: 'companies',
            intervalMinutes: 60,
            indicators: [new IndicatorDefinition('firms.count', 'عدد الشركات الموثّقة في ويكيداتا', 'Companies documented in Wikidata', 'شركة', 'A', [new Range(0, 1e6, 'reject')])],
            reliability: 'secondary',
        );
    }

    public function probe(): ProbeResult
    {
        return new ProbeResult(token: null);
    }

    /** @return array<string, string> ISO3 => QID للدول القائمة */
    private function countries(): array
    {
        $q = 'SELECT ?c ?iso WHERE { ?c wdt:P298 ?iso ; wdt:P31 wd:Q6256 . FILTER NOT EXISTS { ?c wdt:P576 [] } }';
        $rows = $this->sparql($q);
        $out = [];
        foreach ($rows as $r) {
            $out[$r['iso']['value']] = basename($r['c']['value']);
        }
        ksort($out);

        return $out;
    }

    /** @return list<array<string, array{value: string}>> */
    private function sparql(string $query): array
    {
        $response = $this->httpAccepting('application/sparql-results+json')->timeout(120)->retry(4, fn (int $a): int => $a * 15_000)->asForm()->post(self::SPARQL, ['query' => $query])->throw();
        $res = $response->json();
        if (! is_array($res) || ! isset($res['results']['bindings'])) {
            throw new RuntimeException('Wikidata SPARQL: تغيّر شكل الاستجابة: '.mb_substr(trim($response->body()), 0, 200));
        }

        return $res['results']['bindings'];
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, mixed>
     */
    private function entities(array $ids, string $props): array
    {
        $res = $this->httpAccepting('application/json')->timeout(90)->retry(4, fn (int $a): int => $a * 10_000)->get(self::API, [
            'action' => 'wbgetentities', 'ids' => implode('|', $ids), 'props' => $props, 'languages' => 'ar|en', 'format' => 'json', 'maxlag' => 5,
        ])->throw()->json();
        if (isset($res['error'])) {
            throw new RuntimeException('Wikidata API: '.json_encode($res['error'], JSON_UNESCAPED_UNICODE));
        }

        return $res['entities'] ?? [];
    }

    public function fetch(): RawPayload
    {
        $startedAt = time();
        $countries = $this->countries();
        $slice = intdiv(time(), 3600) % self::SLICES;
        $chosen = [];
        $i = 0;
        foreach ($countries as $iso => $qid) {
            if ($i++ % self::SLICES === $slice) {
                $chosen[$iso] = $qid;
            }
        }

        $firmsByCountry = [];
        $all = [];
        $failed = 0;
        $classes = implode(' ', array_map(fn (string $c) => "wd:{$c}", self::CLASSES));
        foreach ($chosen as $iso => $qid) {
            if (time() - $startedAt > self::BUDGET) {
                break;
            }
            $q = "SELECT DISTINCT ?c WHERE { VALUES ?cls { {$classes} } ?c wdt:P31 ?cls ; wdt:P17 wd:{$qid} . FILTER(EXISTS{?c wdt:P1128 []} || EXISTS{?c wdt:P2139 []} || EXISTS{?c wdt:P414 []} || EXISTS{?c wdt:P2226 []}) } LIMIT ".self::PER_COUNTRY;
            try {
                $ids = array_values(array_unique(array_map(fn ($r) => basename($r['c']['value']), $this->sparql($q))));
            } catch (\Throwable) {
                $failed++; // دولة تعذّر استعلامها تُترك لجولتها القادمة؛ الفشل الكامل وحده يُسقط الجولة.

                continue;
            }
            $firmsByCountry[$iso] = $ids;
            array_push($all, ...$ids);
            sleep(2);
        }

        if ($chosen !== [] && $failed === count($chosen)) {
            throw new RuntimeException('Wikidata: فشلت استعلامات كل دول الشريحة.');
        }

        $firms = [];
        $linked = [];
        foreach (array_chunk($all, 50) as $chunk) {
            if (time() - $startedAt > self::BUDGET + 600) {
                break;
            }
            try {
                $details = $this->entities($chunk, 'labels|claims|sitelinks');
            } catch (\Throwable) {
                continue; // دفعة تعذّرت بعد إعادة المحاولات تُترك لجولة الدولة القادمة
            }
            foreach ($details as $id => $e) {
                if (! isset($e['claims'])) {
                    continue;
                }
                $firm = $this->extract($e);
                $firms[$id] = $firm;
                foreach ($firm['refs'] as $ref) {
                    $linked[$ref] = true;
                }
            }
            usleep(400_000);
        }

        $labels = [];
        foreach (array_chunk(array_keys($linked), 50) as $chunk) {
            try {
                $named = $this->entities($chunk, 'labels');
            } catch (\Throwable) {
                continue;
            }
            foreach ($named as $id => $e) {
                $labels[$id] = ['en' => $e['labels']['en']['value'] ?? null, 'ar' => $e['labels']['ar']['value'] ?? null];
            }
            usleep(400_000);
        }

        $currencies = [];
        foreach (array_chunk($this->currencyIds($firms), 50) as $chunk) {
            try {
                $units = $this->entities($chunk, 'claims');
            } catch (\Throwable) {
                continue;
            }
            foreach ($units as $id => $e) {
                $currencies[$id] = $e['claims']['P498'][0]['mainsnak']['datavalue']['value'] ?? null;
            }
            usleep(400_000);
        }

        return new RawPayload(json_encode(['slice' => $slice, 'year' => gmdate('Y'), 'by_country' => $firmsByCountry, 'firms' => $firms, 'labels' => $labels, 'currencies' => $currencies], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, array<string, mixed>>  $firms
     * @return list<string>
     */
    private function currencyIds(array $firms): array
    {
        $ids = [];
        foreach ($firms as $f) {
            foreach (['revenue', 'assets', 'market_cap'] as $k) {
                if (isset($f[$k]['unit'])) {
                    $ids[$f[$k]['unit']] = true;
                }
            }
        }

        return array_keys($ids);
    }

    /**
     * يستخرج حقول الشركة من كيان ويكيداتا (أحدث قيمة لكل خاصية: المفضَّلة ثم الأحدث تاريخًا).
     *
     * @param  array<string, mixed>  $e
     * @return array<string, mixed>
     */
    public function extract(array $e): array
    {
        $c = $e['claims'];
        $firm = [
            'en' => $e['labels']['en']['value'] ?? null,
            'ar' => $e['labels']['ar']['value'] ?? null,
            'inception' => $this->time($c['P571'] ?? []),
            'employees' => $this->quantity($c['P1128'] ?? [])['amount'] ?? null,
            'revenue' => $this->quantity($c['P2139'] ?? []),
            'assets' => $this->quantity($c['P2403'] ?? []),
            'market_cap' => $this->quantity($c['P2226'] ?? []),
            'website' => $this->string($c['P856'] ?? []),
            'ticker' => $this->string($c['P249'] ?? []),
            'lei' => $this->string($c['P1278'] ?? []),
            'logo' => $this->string($c['P154'] ?? []),
            'industry' => $this->items($c['P452'] ?? [], 4),
            'hq' => $this->items($c['P159'] ?? [], 1)[0] ?? null,
            'exchange' => $this->items($c['P414'] ?? [], 3),
            'ceo' => $this->items($c['P169'] ?? [], 1)[0] ?? null,
            'parent' => $this->items($c['P749'] ?? [], 1)[0] ?? null,
            'legal_form' => $this->items($c['P1454'] ?? [], 1)[0] ?? null,
            'wiki_ar' => isset($e['sitelinks']['arwiki']) ? $e['sitelinks']['arwiki']['title'] : null,
            'wiki_en' => isset($e['sitelinks']['enwiki']) ? $e['sitelinks']['enwiki']['title'] : null,
        ];
        $firm['refs'] = array_values(array_unique(array_filter(array_merge($firm['industry'], $firm['exchange'], [$firm['hq'], $firm['ceo'], $firm['parent'], $firm['legal_form']]))));

        return $firm;
    }

    /**
     * @param  list<array<string, mixed>>  $statements
     * @return list<array<string, mixed>>
     */
    private function best(array $statements): array
    {
        $usable = array_values(array_filter($statements, fn ($s) => ($s['rank'] ?? 'normal') !== 'deprecated' && isset($s['mainsnak']['datavalue'])));
        $preferred = array_values(array_filter($usable, fn ($s) => $s['rank'] === 'preferred'));
        $pool = $preferred ?: $usable;
        usort($pool, fn ($a, $b) => strcmp((string) ($b['qualifiers']['P585'][0]['datavalue']['value']['time'] ?? ''), (string) ($a['qualifiers']['P585'][0]['datavalue']['value']['time'] ?? '')));

        return $pool;
    }

    /**
     * @param  list<array<string, mixed>>  $statements
     * @return array{amount: float, unit: ?string, year: ?string}|null
     */
    private function quantity(array $statements): ?array
    {
        $s = $this->best($statements)[0] ?? null;
        $v = $s['mainsnak']['datavalue']['value'] ?? null;
        if (! is_array($v) || ! isset($v['amount']) || ! is_numeric($v['amount'])) {
            return null;
        }
        $unit = isset($v['unit']) && str_contains((string) $v['unit'], '/entity/') ? basename((string) $v['unit']) : null;
        $when = $s['qualifiers']['P585'][0]['datavalue']['value']['time'] ?? null;

        return ['amount' => (float) $v['amount'], 'unit' => $unit, 'year' => is_string($when) && preg_match('/(\d{4})/', $when, $m) ? $m[1] : null];
    }

    /** @param  list<array<string, mixed>>  $statements */
    private function time(array $statements): ?string
    {
        $t = $this->best($statements)[0]['mainsnak']['datavalue']['value']['time'] ?? null;

        return is_string($t) && preg_match('/^[+-]?(\d{4})/', $t, $m) && (int) $m[1] > 0 ? $m[1] : null;
    }

    /** @param  list<array<string, mixed>>  $statements */
    private function string(array $statements): ?string
    {
        $v = $this->best($statements)[0]['mainsnak']['datavalue']['value'] ?? null;

        return is_string($v) && $v !== '' ? mb_substr($v, 0, 300) : null;
    }

    /**
     * @param  list<array<string, mixed>>  $statements
     * @return list<string>
     */
    private function items(array $statements, int $limit): array
    {
        $out = [];
        foreach ($this->best($statements) as $s) {
            $id = $s['mainsnak']['datavalue']['value']['id'] ?? null;
            if (is_string($id)) {
                $out[] = $id;
            }
        }

        return array_slice(array_values(array_unique($out)), 0, $limit);
    }

    public function parse(RawPayload $payload): iterable
    {
        $d = json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR);
        $label = fn (?string $id, string $lang = 'en'): ?string => $id === null ? null : ($d['labels'][$id][$lang] ?? $d['labels'][$id]['en'] ?? null);
        $labelAr = fn (?string $id): ?string => $id === null ? null : ($d['labels'][$id]['ar'] ?? null);
        $money = function (?array $q) use ($d): ?array {
            if ($q === null) {
                return null;
            }
            $cur = $q['unit'] !== null ? ($d['currencies'][$q['unit']] ?? null) : null;

            return ['amount' => $q['amount'], 'currency' => is_string($cur) ? $cur : null, 'year' => $q['year']];
        };

        $isoOf = [];
        foreach ($d['by_country'] as $iso => $ids) {
            foreach ($ids as $id) {
                $isoOf[$id] = $iso;
            }
            yield new ParsedRecord('firms.count', (string) $iso, (string) $d['year'], (float) count($ids));
        }

        foreach ($d['firms'] as $id => $f) {
            $name = $f['en'] ?? $f['ar'] ?? null;
            if ($name === null) {
                continue;
            }
            $code = "wd:{$id}";
            yield new ParsedEntity($code, mb_substr((string) $name, 0, 250), 'firm', $f['ar'] !== null ? mb_substr((string) $f['ar'], 0, 250) : null);

            $attrs = [
                'country' => $isoOf[$id] ?? null, 'inception' => $f['inception'], 'employees' => $f['employees'] !== null ? (int) $f['employees'] : null,
                'revenue' => $money($f['revenue']), 'assets' => $money($f['assets']), 'market_cap' => $money($f['market_cap']),
                'website' => $this->url($f['website']), 'ticker' => $f['ticker'], 'lei' => $f['lei'],
                'logo' => $f['logo'] ? 'https://commons.wikimedia.org/wiki/Special:FilePath/'.rawurlencode($f['logo']).'?width=120' : null,
                'industry' => array_values(array_filter(array_map(fn ($i) => $label($i), $f['industry']))) ?: null,
                'industry_ar' => array_values(array_filter(array_map(fn ($i) => $labelAr($i), $f['industry']))) ?: null,
                'hq' => $label($f['hq']), 'hq_ar' => $labelAr($f['hq']),
                'exchange' => array_values(array_filter(array_map(fn ($i) => $label($i), $f['exchange']))) ?: null,
                'ceo' => $label($f['ceo']), 'parent' => $label($f['parent']), 'parent_ar' => $labelAr($f['parent']), 'legal_form' => $label($f['legal_form']),
                'wikipedia_en' => $f['wiki_en'] ? 'https://en.wikipedia.org/wiki/'.str_replace(' ', '_', $f['wiki_en']) : null,
                'wikipedia_ar' => $f['wiki_ar'] ? 'https://ar.wikipedia.org/wiki/'.str_replace(' ', '_', $f['wiki_ar']) : null,
                'wikidata_url' => "https://www.wikidata.org/wiki/{$id}",
            ];
            foreach ($attrs as $k => $v) {
                if ($v !== null && $v !== '' && $v !== []) {
                    yield new ParsedAttribute($code, $k, $v);
                }
            }
        }
    }

    private function url(?string $u): ?string
    {
        if ($u === null || $u === '') {
            return null;
        }

        return filter_var($u, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $u) ? $u : null;
    }
}
