<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Api\DataVersion;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\ValueChain\ChainAnalytics;
use App\Modules\ValueChain\ChainRepository;
use App\Modules\ValueChain\SeriesReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * لوحة إدارة سلاسل القيمة: القراءة لأي مستخدم مسجّل، والتعديل لمدير النظام أو المحلل. كل تعديل يرفع رقم النسخة ويحفظ لقطة كاملة.
 */
class ChainController extends Controller
{
    public function __construct(private ChainRepository $repo) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->repo->list()]);
    }

    public function show(string $slug): JsonResponse
    {
        $full = $this->repo->full($slug);
        abort_if($full === null, 404, 'سلسلة غير موجودة.');

        return response()->json($full);
    }

    /** GET /intel/chains/{slug}/analytics — التقلب والتركّز والمخاطر لكل عقدة، والتأخر الزمني لكل مدخل حتى المنتج النهائي. */
    public function analytics(string $slug): JsonResponse
    {
        $chain = $this->chain($slug);
        $key = 'chain-analytics:'.$chain->id.':'.$chain->version.':'.DataVersion::token();

        return response()->json(Cache::remember($key, 3600, fn () => app(ChainAnalytics::class)->analyse((int) $chain->id)));
    }

    /** GET /intel/chains/{slug}/history?series_id=&from= — تاريخ سلسلة سعرية مرتبطة بالسلسلة مع بطاقتها. */
    public function history(Request $request, string $slug): JsonResponse
    {
        $chain = $this->chain($slug);
        $d = $request->validate(['series_id' => ['required', 'integer'], 'from' => ['nullable', 'regex:/^\d{4}(-\d{2})?$/']]);
        $series = DB::table('intel.price_series as s')->join('intel.node_series as ns', 'ns.series_id', '=', 's.id')->join('intel.chain_nodes as n', 'n.id', '=', 'ns.node_id')
            ->where('s.id', $d['series_id'])->where('n.chain_id', $chain->id)->first(['s.*']);
        abort_if(! $series, 404);
        $reader = new SeriesReader;

        return response()->json([
            'series' => $series,
            'points' => collect($reader->points($series->indicator_key, $series->entity_code, $d['from'] ?? null))->map(fn ($v, $p) => [$p, $v])->values(),
            'latest' => $reader->latest($series->indicator_key, $series->entity_code),
        ]);
    }

    /**
     * GET /intel/early-warning — لوحة الإنذار المبكر: آخر توقع لكل عقدة وأفق، مع المدخل المسبّب ونطاق الثقة والتوصية،
     * وربطها بمنتجات الجهة، وسجل دقة التوقعات السابقة.
     */
    public function earlyWarning(Request $request): JsonResponse
    {
        $orgNodes = [];
        $memberships = $request->user()->memberships();
        if ($memberships !== []) {
            $orgNodes = DB::table('app.supplier_prices')->whereIn('organization_id', array_column($memberships, 'organization_id'))->whereNotNull('node_id')
                ->selectRaw('node_id, count(*) n, max(product_label) label')->groupBy('node_id')->get()->keyBy('node_id');
        }
        $latest = DB::table('intel.forecasts as f')->join('intel.chain_nodes as n', 'n.id', '=', 'f.node_id')->join('intel.value_chains as c', 'c.id', '=', 'f.chain_id')
            ->where('f.method', 'not like', '%_backtest')
            ->whereRaw("f.issued_at = (SELECT max(f2.issued_at) FROM intel.forecasts f2 WHERE f2.node_id = f.node_id AND f2.method NOT LIKE '%_backtest')")
            ->orderBy('c.id')->orderBy('n.stage')->get(['f.*', 'n.name_ar', 'n.kind', 'c.slug', 'c.name_ar as chain_name']);
        $trade = DB::table('intel.node_trade')->pluck('hhi', 'node_id');
        $rows = [];
        foreach ($latest->groupBy('node_id') as $nodeId => $fs) {
            $f0 = $fs->first();
            $h = $fs->keyBy('horizon_months')->map(fn ($f) => ['expected_pct' => (float) $f->expected_pct, 'low_pct' => (float) $f->low_pct, 'high_pct' => (float) $f->high_pct, 'drivers' => json_decode($f->drivers, true)]);
            $h3 = $h[3]['expected_pct'] ?? 0.0;
            $recs = [];
            if ($h3 >= 3) {
                $recs[] = 'قدّم موعد الشراء أو ثبّت السعر بعقد قبل انتقال ارتفاع المدخلات.';
            } elseif ($h3 <= -3) {
                $recs[] = 'أجّل الشراء غير العاجل: المدخلات تشير إلى انخفاض قادم.';
            }
            if (($trade[$nodeId] ?? 0) >= 2500) {
                $recs[] = 'نوّع الموردين: الدول المصدّرة شديدة التركّز.';
            }
            $acc = DB::table('intel.forecasts')->where('node_id', $nodeId)->where('horizon_months', 3)->whereNotNull('actual_pct')->get(['expected_pct', 'actual_pct', 'low_pct', 'high_pct', 'method']);
            $skill = $acc->isEmpty() || $acc->avg(fn ($a) => abs($a->actual_pct)) == 0 ? null : 1 - $acc->avg(fn ($a) => abs($a->expected_pct - $a->actual_pct)) / $acc->avg(fn ($a) => abs($a->actual_pct));
            if ($skill !== null && $skill <= 0) {
                // النموذج لا يتفوق على «لا تغيير» لهذا المنتج: لا نوصي بإجراء بناءً عليه
                $recs = array_values(array_filter($recs, fn ($r) => ! str_contains($r, 'الشراء')));
            }
            $rows[] = [
                'node_id' => $nodeId, 'node' => $f0->name_ar, 'chain' => $f0->chain_name, 'chain_slug' => $f0->slug, 'kind' => $f0->kind, 'target_series' => $f0->target_series,
                'base_period' => $f0->base_period, 'issued_at' => $f0->issued_at, 'fit' => json_decode($f0->fit, true), 'horizons' => $h, 'recommendations' => $recs,
                'skill' => $skill === null ? null : round($skill, 2),
                'org_purchase' => isset($orgNodes[$nodeId]) ? ['count' => $orgNodes[$nodeId]->n, 'label' => $orgNodes[$nodeId]->label] : null,
                'accuracy' => $acc->isEmpty() ? null : ['n' => $acc->count(), 'mae_pts' => round($acc->avg(fn ($a) => abs($a->expected_pct - $a->actual_pct)), 2),
                    'direction_hit' => round($acc->filter(fn ($a) => ($a->expected_pct >= 0) === ($a->actual_pct >= 0))->count() / $acc->count(), 2),
                    'backtest' => $acc->filter(fn ($a) => str_ends_with($a->method, '_backtest'))->count(),
                    'naive_mae_pts' => round($acc->avg(fn ($a) => abs($a->actual_pct)), 2),
                    'in_band' => round($acc->filter(fn ($a) => $a->actual_pct >= $a->low_pct && $a->actual_pct <= $a->high_pct)->count() / $acc->count(), 2)],
            ];
        }
        usort($rows, fn ($a, $b) => ($b['org_purchase'] ? 1 : 0) <=> ($a['org_purchase'] ? 1 : 0) ?: abs($b['horizons'][3]['expected_pct'] ?? 0) <=> abs($a['horizons'][3]['expected_pct'] ?? 0));

        return response()->json(['data' => $rows, 'method_note' => 'انحدار بتأخرات موزعة (0–6 أشهر) لتغير سعر العقدة على تغيرات مدخلاتها؛ التوقع = أثر ما حدث فعلًا في المدخلات ولم ينتقل بعد، بافتراض ثبات المدخلات من الآن. النطاق 90% من بواقي النموذج.']);
    }

    /** GET /intel/nodes — كل العقد مع سلاسلها (لربط أسعار الجهة بعقدة). */
    public function nodes(): JsonResponse
    {
        return response()->json(['data' => DB::table('intel.chain_nodes as n')->join('intel.value_chains as c', 'c.id', '=', 'n.chain_id')->where('n.kind', '<>', 'conversion')
            ->orderBy('c.id')->orderBy('n.stage')->get(['n.id', 'n.name_ar', 'n.unit', 'n.kind', 'n.hs_codes', 'c.slug as chain_slug', 'c.name_ar as chain_name'])]);
    }

    public function revisions(string $slug): JsonResponse
    {
        $chain = $this->chain($slug);

        return response()->json(['data' => DB::table('intel.chain_revisions as r')->leftJoin('app.users as u', 'u.id', '=', 'r.edited_by')->where('r.chain_id', $chain->id)
            ->orderByDesc('r.id')->limit(50)->get(['r.id', 'r.version', 'r.note', 'r.created_at', 'u.name as editor'])]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeEdit($request);
        $d = $request->validate(['slug' => ['required', 'regex:/^[a-z0-9-]{3,60}$/', 'unique:pgsql.intel.value_chains,slug'], 'name_ar' => ['required', 'string', 'max:160'], 'name_en' => ['required', 'string', 'max:160'], 'description' => ['nullable', 'string', 'max:2000']]);
        $id = DB::table('intel.value_chains')->insertGetId($d + ['status' => 'draft', 'updated_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->repo->revise($id, $request->user()->id, 'إنشاء سلسلة');

        return response()->json($this->repo->full($d['slug']), 201);
    }

    public function updateChain(Request $request, string $slug): JsonResponse
    {
        $this->authorizeEdit($request);
        $chain = $this->chain($slug);
        $d = $request->validate(['name_ar' => ['sometimes', 'string', 'max:160'], 'name_en' => ['sometimes', 'string', 'max:160'], 'description' => ['sometimes', 'nullable', 'string', 'max:2000'], 'route_note' => ['sometimes', 'nullable', 'string', 'max:2000'], 'status' => ['sometimes', Rule::in(['draft', 'review', 'published'])]]);
        DB::table('intel.value_chains')->where('id', $chain->id)->update($d + ['updated_at' => now()]);

        return $this->saved($request, $chain, 'تعديل بيانات السلسلة');
    }

    public function storeNode(Request $request, string $slug): JsonResponse
    {
        $this->authorizeEdit($request);
        $chain = $this->chain($slug);
        $d = $request->validate($this->nodeRules(true));
        DB::table('intel.chain_nodes')->insert($this->nodeRow($d) + ['chain_id' => $chain->id]);

        return $this->saved($request, $chain, "إضافة عقدة {$d['name_ar']}");
    }

    public function updateNode(Request $request, string $slug, int $node): JsonResponse
    {
        $this->authorizeEdit($request);
        $chain = $this->chain($slug);
        $row = $this->owned('intel.chain_nodes', $node, $chain->id);
        $d = $request->validate($this->nodeRules(false));
        DB::table('intel.chain_nodes')->where('id', $row->id)->update($this->nodeRow($d));

        return $this->saved($request, $chain, "تعديل عقدة {$row->name_ar}");
    }

    public function destroyNode(Request $request, string $slug, int $node): JsonResponse
    {
        $this->authorizeEdit($request);
        $chain = $this->chain($slug);
        $row = $this->owned('intel.chain_nodes', $node, $chain->id);
        DB::table('intel.chain_nodes')->where('id', $row->id)->delete();

        return $this->saved($request, $chain, "حذف عقدة {$row->name_ar}");
    }

    public function storeEdge(Request $request, string $slug): JsonResponse
    {
        $this->authorizeEdit($request);
        $chain = $this->chain($slug);
        $d = $request->validate(['from_node_id' => ['required', 'integer'], 'to_node_id' => ['required', 'integer', 'different:from_node_id']] + $this->edgeRules());
        $this->owned('intel.chain_nodes', (int) $d['from_node_id'], $chain->id);
        $this->owned('intel.chain_nodes', (int) $d['to_node_id'], $chain->id);
        $this->checkWeights($d);
        DB::table('intel.chain_edges')->insert($d + ['chain_id' => $chain->id, 'updated_by' => $request->user()->id, 'updated_at' => now()]);

        return $this->saved($request, $chain, 'إضافة حافة');
    }

    public function updateEdge(Request $request, string $slug, int $edge): JsonResponse
    {
        $this->authorizeEdit($request);
        $chain = $this->chain($slug);
        $row = $this->owned('intel.chain_edges', $edge, $chain->id);
        $d = $request->validate($this->edgeRules());
        $this->checkWeights($d + (array) $row);
        DB::table('intel.chain_edges')->where('id', $row->id)->update($d + ['updated_by' => $request->user()->id, 'updated_at' => now()]);

        return $this->saved($request, $chain, (string) $request->input('change_note', 'تعديل وزن/عامل تحويل'));
    }

    public function destroyEdge(Request $request, string $slug, int $edge): JsonResponse
    {
        $this->authorizeEdit($request);
        $chain = $this->chain($slug);
        DB::table('intel.chain_edges')->where('id', $this->owned('intel.chain_edges', $edge, $chain->id)->id)->delete();

        return $this->saved($request, $chain, 'حذف حافة');
    }

    public function storeSource(Request $request, string $slug, int $edge): JsonResponse
    {
        $this->authorizeEdit($request);
        $chain = $this->chain($slug);
        $row = $this->owned('intel.chain_edges', $edge, $chain->id);
        $d = $request->validate([
            'title' => ['required', 'string', 'max:300'], 'url' => ['nullable', 'url:https', 'max:600'], 'publisher' => ['nullable', 'string', 'max:160'], 'year' => ['nullable', 'integer', 'between:1950,2100'],
            'kind' => ['required', Rule::in(['industry', 'academic', 'agency', 'company_report', 'io_table', 'vendor', 'trade_press', 'derived'])], 'reliability' => ['required', Rule::in(['high', 'medium', 'low'])],
            'applies_to' => ['required', Rule::in(['weight', 'conversion', 'both'])], 'excerpt' => ['nullable', 'string', 'max:2000'],
        ]);
        DB::table('intel.edge_sources')->insert($d + ['edge_id' => $row->id]);

        return $this->saved($request, $chain, "إضافة مصدر: {$d['title']}");
    }

    public function destroySource(Request $request, string $slug, int $source): JsonResponse
    {
        $this->authorizeEdit($request);
        $chain = $this->chain($slug);
        $src = DB::table('intel.edge_sources as s')->join('intel.chain_edges as e', 'e.id', '=', 's.edge_id')->where('s.id', $source)->where('e.chain_id', $chain->id)->first(['s.id', 's.title']);
        abort_if(! $src, 404);
        DB::table('intel.edge_sources')->where('id', $src->id)->delete();

        return $this->saved($request, $chain, "حذف مصدر: {$src->title}");
    }

    /** ربط سلسلة سعرية موجودة في النظام بعقدة (مع بطاقتها: نوع السعر والموثوقية والسوق وشروط التسليم). */
    public function attachSeries(Request $request, string $slug, int $node): JsonResponse
    {
        $this->authorizeEdit($request);
        $chain = $this->chain($slug);
        $row = $this->owned('intel.chain_nodes', $node, $chain->id);
        $d = $request->validate([
            'indicator_key' => ['required', 'string', 'max:128'], 'entity_code' => ['required', 'string', 'max:32'], 'label_ar' => ['required', 'string', 'max:200'],
            'price_type' => ['required', Rule::in(['reference', 'index', 'customs_unit_value', 'futures', 'supplier', 'asking', 'derived'])], 'confidence' => ['required', Rule::in(['high', 'medium', 'low'])],
            'market' => ['nullable', 'string', 'max:80'], 'incoterm' => ['nullable', Rule::in(['FOB', 'CFR', 'CIF', 'EXW', 'DAP', 'DDP', 'FCA'])], 'unit' => ['nullable', 'string', 'max:40'],
            'currency' => ['nullable', 'regex:/^[A-Z]{3}$/'], 'role' => ['required', Rule::in(['primary', 'alternative', 'proxy'])],
        ]);
        $frequency = SeriesReader::isUpload($d['indicator_key'])
            ? DB::table('intel.uploaded_series')->where('key', $d['indicator_key'])->value('frequency')
            : DB::table('core.indicators')->where('key', $d['indicator_key'])->value('frequency');
        if (! $frequency || ! (new SeriesReader)->exists($d['indicator_key'], strtoupper($d['entity_code']))) {
            throw ValidationException::withMessages(['indicator_key' => 'لا توجد بيانات لهذا المؤشر وهذا الكيان في النظام.']);
        }
        $seriesId = DB::table('intel.price_series')->where('indicator_key', $d['indicator_key'])->where('entity_code', strtoupper($d['entity_code']))->value('id')
            ?? DB::table('intel.price_series')->insertGetId([
                'indicator_key' => $d['indicator_key'], 'entity_code' => strtoupper($d['entity_code']), 'label_ar' => $d['label_ar'], 'price_type' => $d['price_type'], 'confidence' => $d['confidence'],
                'market' => $d['market'] ?? null, 'incoterm' => $d['incoterm'] ?? null, 'unit' => $d['unit'] ?? null, 'currency' => $d['currency'] ?? null, 'frequency' => $frequency, 'created_at' => now(), 'updated_at' => now(),
            ]);
        $priority = (int) DB::table('intel.node_series')->where('node_id', $row->id)->max('priority') + 1;
        DB::table('intel.node_series')->updateOrInsert(['node_id' => $row->id, 'series_id' => $seriesId], ['role' => $d['role'], 'priority' => $priority]);

        return $this->saved($request, $chain, "ربط سلسلة سعرية {$d['indicator_key']} بعقدة {$row->name_ar}");
    }

    public function detachSeries(Request $request, string $slug, int $node, int $series): JsonResponse
    {
        $this->authorizeEdit($request);
        $chain = $this->chain($slug);
        $row = $this->owned('intel.chain_nodes', $node, $chain->id);
        DB::table('intel.node_series')->where('node_id', $row->id)->where('series_id', $series)->delete();

        return $this->saved($request, $chain, "فك سلسلة سعرية من عقدة {$row->name_ar}");
    }

    /** GET /intel/indicators?q= — البحث في مؤشرات النظام لربطها بالعقد. */
    public function indicators(Request $request): JsonResponse
    {
        $q = trim((string) $request->validate(['q' => ['required', 'string', 'min:2', 'max:80']])['q']);

        $core = DB::table('core.indicators as i')->join('ops.datasets as d', 'd.id', '=', 'i.dataset_id')
            ->where(fn ($w) => $w->where('i.key', 'ilike', "%{$q}%")->orWhere('i.name_ar', 'ilike', "%{$q}%")->orWhere('i.name_en', 'ilike', "%{$q}%"))
            ->orderBy('i.key')->limit(25)->get(['i.key', 'i.name_ar', 'i.unit', 'i.frequency', 'd.key as dataset']);
        $uploads = DB::table('intel.uploaded_series')->where(fn ($w) => $w->where('key', 'ilike', "%{$q}%")->orWhere('label_ar', 'ilike', "%{$q}%")->orWhere('source_name', 'ilike', "%{$q}%"))
            ->limit(15)->get()->map(fn ($s) => ['key' => $s->key, 'name_ar' => $s->label_ar, 'unit' => $s->unit, 'frequency' => $s->frequency, 'dataset' => "رفع يدوي: {$s->source_name}", 'entity' => $s->entity_code]);

        return response()->json(['data' => [...$uploads->all(), ...$core->all()]]);
    }

    /** @return array<string, mixed> */
    private function edgeRules(): array
    {
        $w = ['nullable', 'numeric', 'between:0,1'];

        return [
            'weight' => $w, 'weight_min' => $w, 'weight_max' => $w,
            'weight_basis' => ['sometimes', Rule::in(['documented_estimate', 'derived', 'io_table', 'annual_report', 'regression', 'analyst', 'missing'])],
            'conversion_factor' => ['nullable', 'numeric', 'min:0'], 'conversion_min' => ['nullable', 'numeric', 'min:0'], 'conversion_max' => ['nullable', 'numeric', 'min:0'],
            'conversion_unit' => ['nullable', 'string', 'max:60'], 'notes' => ['nullable', 'string', 'max:2000'], 'logistics_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @param  array<string, mixed>  $d */
    private function checkWeights(array $d): void
    {
        $w = $d['weight'] ?? null;
        $lo = $d['weight_min'] ?? null;
        $hi = $d['weight_max'] ?? null;
        if ($lo !== null && $hi !== null && (float) $lo > (float) $hi) {
            throw ValidationException::withMessages(['weight_min' => 'الحد الأدنى أكبر من الأعلى.']);
        }
        if ($w !== null && (($lo !== null && (float) $w < (float) $lo) || ($hi !== null && (float) $w > (float) $hi))) {
            throw ValidationException::withMessages(['weight' => 'الوزن خارج نطاقه (أدنى–أعلى).']);
        }
    }

    /** @return array<string, mixed> */
    private function nodeRules(bool $create): array
    {
        $req = $create ? 'required' : 'sometimes';

        return [
            'slug' => [$req, 'regex:/^[a-z0-9-]{2,60}$/'], 'name_ar' => [$req, 'string', 'max:160'], 'name_en' => [$req, 'string', 'max:160'],
            'kind' => [$req, Rule::in(['raw', 'energy', 'intermediate', 'final', 'consumable', 'conversion'])], 'stage' => [$req, 'integer', 'between:0,20'],
            'unit' => ['nullable', 'string', 'max:40'], 'hs_codes' => ['sometimes', 'array', 'max:12'], 'hs_codes.*' => ['regex:/^\d{4,10}$/'], 'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @param  array<string, mixed>  $d @return array<string, mixed> */
    private function nodeRow(array $d): array
    {
        if (array_key_exists('hs_codes', $d)) {
            $d['hs_codes'] = '{'.implode(',', $d['hs_codes']).'}';
        }

        return $d;
    }

    private function authorizeEdit(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user && $user->canEditChains(), 403, 'تعديل سلاسل القيمة لمدير النظام أو المحلل فقط.');
    }

    private function chain(string $slug): object
    {
        $c = DB::table('intel.value_chains')->where('slug', $slug)->first();
        abort_if(! $c, 404, 'سلسلة غير موجودة.');

        return $c;
    }

    private function owned(string $table, int $id, int $chainId): object
    {
        $row = DB::table($table)->where('id', $id)->where('chain_id', $chainId)->first();
        abort_if(! $row, 404);

        return $row;
    }

    private function saved(Request $request, object $chain, string $note): JsonResponse
    {
        $this->repo->revise((int) $chain->id, (int) $request->user()->id, $note);

        return response()->json($this->repo->full((string) $chain->slug));
    }
}
