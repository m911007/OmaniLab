<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Api\DataVersion;
use App\Core\Insights\IndicatorCoverage;
use App\Http\Controllers\Controller;
use App\Http\Resources\DatasetResource;
use App\Models\Indicator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * قاموس المؤشرات والبحث فيها وصفحة المؤشر الواحد (المرحلة 4 من خطة «من الأرقام إلى القرار»).
 */
class IndicatorController extends Controller
{
    /** أحرف تُوحَّد قبل البحث: أ/إ/آ ← ا، ى ← ي، ة ← ه، ويُحذف التطويل والتشكيل. */
    private const FROM = 'أإآىةـًٌٍَُِّْ';

    private const TO = 'ااايه';

    public static function normalise(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = strtr($text, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ى' => 'ي', 'ة' => 'ه']);

        return (string) preg_replace('/[\x{0640}\x{064B}-\x{0652}]/u', '', $text);
    }

    /** تعبير SQL يوحّد عمودًا بالطريقة نفسها. */
    private static function sqlNorm(string $column): string
    {
        return "translate(lower({$column}), '".self::FROM."', '".self::TO."')";
    }

    /**
     * قاموس المؤشرات: GET /api/v1/indicators?q=&section=&omn=1&page=1
     * لكل مؤشر: الاسم والوحدة والتكرار والمجموعة والمصدر، وآخر فترة، وعدد الكيانات، وهل لعُمان بيانات.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'section' => ['nullable', 'string', 'max:64'],
            'omn' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $coverage = $this->coverage();

        $q = DB::table('core.indicators as i')->join('ops.datasets as d', 'd.id', '=', 'i.dataset_id')->join('core.sources as s', 's.id', '=', 'd.source_id')
            ->select('i.id', 'i.key', 'i.name_ar', 'i.name_en', 'i.unit', 'i.frequency', 'd.key as dataset_key', 'd.name_ar as dataset_name', 'd.section', 's.name_ar as source_name')
            ->whereIn('i.id', array_keys($coverage));
        if (! empty($data['q'])) {
            $term = '%'.self::normalise($data['q']).'%';
            $q->where(fn ($w) => $w->whereRaw(self::sqlNorm('i.name_ar').' like ?', [$term])->orWhereRaw('lower(i.name_en) like ?', [$term])->orWhereRaw('lower(i.key) like ?', [$term]));
        }
        if (! empty($data['section'])) {
            $q->where('d.section', $data['section']);
        }
        if (! empty($data['omn'])) {
            $q->whereIn('i.id', array_keys(array_filter($coverage, fn (array $c) => $c['omn'])));
        }
        $page = $q->orderBy('d.section')->orderBy('i.name_ar')->paginate(50, ['*'], 'page', (int) ($data['page'] ?? 1));

        $sections = DB::table('ops.datasets')->distinct()->orderBy('section')->pluck('section');

        return response()->json([
            'data' => collect($page->items())->map(fn ($r) => [
                'key' => $r->key, 'name_ar' => $r->name_ar, 'name_en' => $r->name_en, 'unit' => $r->unit, 'frequency' => $r->frequency,
                'dataset' => ['key' => $r->dataset_key, 'name_ar' => $r->dataset_name, 'section' => $r->section, 'source' => $r->source_name],
            ] + $coverage[$r->id])->all(),
            'meta' => ['total' => $page->total(), 'page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'sections' => $sections],
        ]);
    }

    /** مؤشر واحد: GET /api/v1/indicators/{key} — البيانات الوصفية والمصدر والتغطية والكيان الافتراضي للعرض. */
    public function show(string $key): JsonResponse
    {
        $indicator = Indicator::where('key', $key)->with('dataset.source')->firstOrFail();
        $coverage = $this->coverage()[$indicator->id] ?? ['last_period' => null, 'entities' => 0, 'omn' => false];

        $entities = Cache::remember('indicator-entities:'.DataVersion::token().':'.$indicator->id, 1800, fn () => DB::table('core.observations as o')
            ->join('core.entities as e', 'e.id', '=', 'o.entity_id')->where('o.indicator_id', $indicator->id)
            ->groupBy('e.code', 'e.name_ar', 'e.name_en', 'e.type')->orderByDesc(DB::raw('count(*)'))->limit(300)
            ->get(['e.code', 'e.name_ar', 'e.name_en', 'e.type', DB::raw('count(*) as points'), DB::raw('max(o.period) as last_period')])
            ->map(fn ($r) => (array) $r)->all());

        $default = $coverage['omn'] ? 'OMN' : ($entities[0]['code'] ?? null);

        return response()->json(['data' => [
            'key' => $indicator->key,
            'name_ar' => $indicator->name_ar,
            'name_en' => $indicator->name_en,
            'unit' => $indicator->unit,
            'frequency' => $indicator->frequency,
            'description' => $indicator->description,
            'dataset' => (new DatasetResource($indicator->dataset))->resolve(),
            'coverage' => $coverage,
            'default_entity' => $default,
            'countries' => count(array_filter($entities, fn (array $e) => $e['type'] === 'country')),
            'entities' => $entities,
        ]]);
    }

    /** بحث شامل: GET /api/v1/search?q= — المؤشرات (بالعربية والإنجليزية والمفتاح، مع تقريب الأخطاء) والدول. */
    public function search(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        $term = self::normalise($data['q']);
        $coverage = $this->coverage();

        $indicators = DB::table('core.indicators as i')->join('ops.datasets as d', 'd.id', '=', 'i.dataset_id')
            ->whereIn('i.id', array_keys($coverage))
            ->where(fn ($w) => $w->whereRaw(self::sqlNorm('i.name_ar').' like ?', ['%'.$term.'%'])
                ->orWhereRaw('lower(i.name_en) like ?', ['%'.$term.'%'])
                ->orWhereRaw('lower(i.key) like ?', ['%'.$term.'%'])
                ->orWhereRaw('similarity('.self::sqlNorm('i.name_ar').', ?) > 0.3', [$term]))
            ->orderByRaw('case when '.self::sqlNorm('i.name_ar').' like ? then 0 else 1 end', [$term.'%'])
            ->orderByRaw('similarity('.self::sqlNorm('i.name_ar').', ?) desc', [$term])
            ->limit(20)
            ->get(['i.id', 'i.key', 'i.name_ar', 'i.unit', 'i.frequency', 'd.name_ar as dataset_name'])
            ->map(fn ($r) => ['key' => $r->key, 'name_ar' => $r->name_ar, 'unit' => $r->unit, 'frequency' => $r->frequency, 'dataset' => $r->dataset_name, 'omn' => $coverage[$r->id]['omn']])
            ->sortByDesc('omn')->values();

        $entities = DB::table('core.entities as e')->where('e.type', 'country')
            ->where(fn ($w) => $w->whereRaw(self::sqlNorm('e.name_ar').' like ?', ['%'.$term.'%'])->orWhereRaw('lower(e.name_en) like ?', ['%'.$term.'%'])->orWhereRaw('lower(e.code) = ?', [$term]))
            ->limit(8)->get(['e.code', 'e.name_ar', 'e.name_en'])->map(fn ($r) => (array) $r);

        return response()->json(['data' => ['indicators' => $indicators, 'entities' => $entities]]);
    }

    /** @return array<int, array{last_period: string, entities: int, omn: bool}> */
    private function coverage(): array
    {
        return app(IndicatorCoverage::class)->all();
    }
}
