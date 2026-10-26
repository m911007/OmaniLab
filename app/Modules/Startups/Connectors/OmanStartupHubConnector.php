<?php

namespace App\Modules\Startups\Connectors;

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
 * الشركات الناشئة في منصة عُمان للشركات الناشئة «عِش» (omanstartuphub.om، مركز عُمان للشركات الناشئة): الاسم والوصف والمرحلة والقطاع والقطاع الفرعي
 * وعدد الموظفين وسنة التأسيس ونوع الشركة وما تبحث عنه. من صفحات القائمة العامة وصفحة كل شركة (robots.txt يسمح)، بوتيرة بطيئة، ويُنسب كل شيء للمنصة.
 * تنبيه 1: شروط الموقع تحفظ حقوق المحتوى للمنصة وشركائها، فنعرض الحقول الوصفية القصيرة مع رابط الشركة الأصلي ولا ننسخ الشعارات.
 * تنبيه 2: القائمة العامة تعرض الصفحة الأولى فقط (9 شركات)؛ بقية الصفحات تردّ «لست مسجّل الدخول»، ولا نُنشئ حسابات ولا نتجاوز تسجيل الدخول.
 * وللحصول على القائمة الكاملة يسحبها مالك الحساب بنفسه (حسابه، وبإذنه) إلى `database/data/startups/oman_hub_snapshot.json` فتُدمج هنا بتاريخ لقطتها.
 * لذلك نسجّل أيضًا العدّادات المعلنة علنًا في الصفحة (عدد الشركات الناشئة وروابط المنظومة) ليظهر الفرق بين المعلن والمتاح.
 */
final class OmanStartupHubConnector extends ApiConnector
{
    private const BASE = 'https://omanstartuphub.om/ar/';

    public function queue(): string
    {
        return 'ingest-heavy';
    }

    public function definition(): DatasetDefinition
    {
        return new DatasetDefinition(
            key: 'startups.oman_hub',
            source: new SourceDefinition(key: 'omanstartuphub', nameAr: 'منصة عُمان للشركات الناشئة', nameEn: 'Oman Startup Hub', publisher: 'مركز عُمان للشركات الناشئة', url: 'https://omanstartuphub.om/ar/startup_companies', license: 'حقوق المحتوى للمنصة — عرض وصفي مع الإسناد', reliability: 'secondary'),
            nameAr: 'الشركات الناشئة المسجّلة في منصة عُمان للشركات الناشئة',
            nameEn: 'Startups registered on the Oman Startup Hub',
            section: 'companies',
            intervalMinutes: 7 * 24 * 60,
            indicators: [
                new IndicatorDefinition('startups.platform_count', 'عدد الشركات الناشئة المعلن في المنصة', 'Startups announced on the platform', 'شركة', 'M', [new Range(0, 100000, 'reject')]),
                new IndicatorDefinition('startups.platform_links', 'عدد الروابط المعلن في منظومة الشركات الناشئة', 'Ecosystem links announced on the platform', 'رابط', 'M', [new Range(0, 100000, 'reject')]),
                new IndicatorDefinition('startups.public_listed', 'الشركات الناشئة الظاهرة علنًا (دون تسجيل دخول)', 'Startups publicly visible without login', 'شركة', 'M', [new Range(0, 100000, 'reject')]),
            ],
            reliability: 'secondary',
        );
    }

    public function probe(): ProbeResult
    {
        return new ProbeResult(token: null);
    }

    public function fetch(): RawPayload
    {
        $listing = $this->httpAccepting('text/html')->timeout(60)->retry(3, 3000)->get(self::BASE.'startup_companies')->throw()->body();
        $counters = [
            'platform_count' => preg_match('#counter[^>]*>\s*(\d+)\s*</div>\s*<p class="text">\s*شركة ناشئة#u', $listing, $m) ? (int) $m[1] : null,
            'platform_links' => preg_match('#counter[^>]*>\s*(\d+)\s*</div>\s*<p class="text">\s*<a[^>]*>روابط#u', $listing, $m) ? (int) $m[1] : null,
        ];
        $cards = [];
        // الصفحة الأولى علنية؛ الثانية فما بعد تتطلب تسجيل الدخول فنتوقف عند أول ردّ لا يحوي بطاقات.
        for ($page = 1; $page <= 60; $page++) {
            $html = $this->httpAccepting('text/html')->timeout(60)->retry(3, 3000)->get(self::BASE.'startups/content', ['page' => $page])->throw()->body();
            $found = $this->parseCards($html);
            if ($found === []) {
                break;
            }
            foreach ($found as $c) {
                $cards[$c['slug']] = $c;
            }
            usleep(500_000);
        }
        if ($cards === []) {
            throw new RuntimeException('منصة الشركات الناشئة: لا بطاقات (تغيّر الشكل؟).');
        }
        foreach ($cards as $slug => $c) {
            $detail = $this->httpAccepting('text/html')->timeout(60)->retry(3, 3000)->get(self::BASE.'startup_companies/'.rawurlencode($slug));
            if ($detail->successful()) {
                $cards[$slug] += $this->parseDetail($detail->body());
            }
            usleep(700_000);
        }

        foreach ($this->snapshot() as $slug => $c) {
            $cards[$slug] = $c + ($cards[$slug] ?? []);
        }

        return new RawPayload(json_encode(['counters' => $counters, 'companies' => array_values($cards)], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /** لقطة القائمة الكاملة التي سحبها مالك الحساب (تتجاوز الصفحة العلنية الأولى). @return array<string, array<string, mixed>> */
    private function snapshot(): array
    {
        $path = (string) config('omanilab.startups_snapshot', base_path('database/data/startups/oman_hub_snapshot.json'));
        if (! is_file($path)) {
            return [];
        }
        $out = [];
        foreach (json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR)['companies'] ?? [] as $c) {
            if (! empty($c['slug'])) {
                $out[(string) $c['slug']] = $c;
            }
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function parseCards(string $html): array
    {
        $out = [];
        foreach (array_slice(explode('card-startup', $html), 1) as $card) {
            if (! preg_match('#<h5 class="mb-3"><a href="https://omanstartuphub\.om/ar/startup_companies/([^"]+)">(.*?)</a></h5>#su', $card, $m)) {
                continue;
            }
            $desc = preg_match('#</h5>\s*<p>(.*?)</p>#su', $card, $d) ? trim(html_entity_decode(strip_tags($d[1]))) : null;
            preg_match_all('#<span class="badge">(.*?)</span>#su', $card, $b);
            $out[] = [
                'slug' => urldecode($m[1]),
                'name' => trim(html_entity_decode(strip_tags($m[2]))),
                'blurb' => $desc,
                'seeking' => array_values(array_filter(array_map(fn ($x) => trim(html_entity_decode(strip_tags($x))), $b[1] ?? []))),
            ];
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function parseDetail(string $html): array
    {
        $clean = fn (string $s) => trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($s))));
        $out = [];
        // وسما حالة الشركة: «غير نشط» (إن وُجد) ثم مرحلة النمو.
        if (preg_match_all('#<div class="details-card-tag">(.*?)</div>#su', $html, $m)) {
            $tags = array_values(array_filter(array_map($clean, $m[1])));
            if (in_array('غير نشط', $tags, true)) {
                $out['status'] = 'غير نشط';
            }
            $stage = array_values(array_diff($tags, ['غير نشط']))[0] ?? null;
            if ($stage !== null) {
                $out['stage'] = $stage;
            }
        }
        foreach (['عدد الموظفين' => 'employees', 'سنة التأسيس' => 'founded', 'نوع الشركة' => 'legal_type'] as $label => $key) {
            if (preg_match('#<h6>([^<]*)</h6>\s*<div>'.preg_quote($label, '#').'</div>#su', $html, $m)) {
                $out[$key] = $clean($m[1]);
            }
        }
        preg_match_all('#<div class="gray-block[^"]*">\s*<h5>(.*?)</h5>(.*?)</div>#su', $html, $blocks, PREG_SET_ORDER);
        foreach ($blocks as $b) {
            preg_match_all('#<span class="tag">(.*?)</span>#su', $b[2], $tags);
            $values = array_values(array_filter(array_map($clean, $tags[1] ?? [])));
            if ($values !== []) {
                $out['tags'][$clean($b[1])] = $values;
            }
        }
        if (preg_match_all('#<p class="text-white">([^<]{20,})</p>#u', $html, $m) && $m[1] !== []) {
            $out['description'] = mb_substr($clean($m[1][0]), 0, 600);
        }

        return $out;
    }

    public function parse(RawPayload $payload): iterable
    {
        $data = json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR);
        $companies = $data['companies'];
        foreach ($companies as $c) {
            $code = 'startup:'.mb_strtolower($c['slug']);
            yield new ParsedEntity($code, $c['name'], 'startup');
            $attrs = [
                'blurb' => $c['blurb'] ?? null, 'description' => $c['description'] ?? null, 'stage' => $c['stage'] ?? null, 'employees' => $c['employees'] ?? null,
                'legal_type' => $c['legal_type'] ?? null, 'activity_status' => $c['status'] ?? null, 'seeking' => ($c['seeking'] ?? []) ?: null, 'url' => 'https://omanstartuphub.om/ar/startup_companies/'.rawurlencode($c['slug']),
                'founded' => $c['founded'] ?? null,
            ];
            if (preg_match('/(\d{4})/', (string) ($c['founded'] ?? ''), $y)) {
                $attrs['founded_year'] = (int) $y[1];
            }
            foreach ($c['tags'] ?? [] as $label => $values) {
                if ($label === 'نبحث عن') {
                    foreach ($values as $v) {
                        if (str_starts_with($v, 'حجم التمويل')) {
                            $attrs['funding_sought'] = trim(preg_replace('/^[^:]*:/u', '', $v));
                        } else {
                            $attrs['seeking'] = array_values(array_unique([...($attrs['seeking'] ?? []), $v]));
                        }
                    }

                    continue;
                }
                $attrs[$label === 'القطاع' ? 'sector' : 'subsector'] = $values;
            }
            foreach ($attrs as $key => $value) {
                if ($value !== null && $value !== '' && $value !== []) {
                    yield new ParsedAttribute($code, $key, $value);
                }
            }
        }
        yield new ParsedEntity('OMN', 'Oman', 'country', 'سلطنة عُمان');
        foreach (['startups.platform_count' => 'platform_count', 'startups.platform_links' => 'platform_links'] as $key => $field) {
            if (isset($data['counters'][$field])) {
                yield new ParsedRecord($key, 'OMN', date('Y-m'), (float) $data['counters'][$field]);
            }
        }
        yield new ParsedRecord('startups.public_listed', 'OMN', date('Y-m'), (float) count($companies));
    }
}
