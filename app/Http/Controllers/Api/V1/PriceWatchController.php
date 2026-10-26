<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Prices\Countries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * تاريخ السعر وقائمة المتابعة (على طريقة إضافات Keepa وCamelCamelCamel): كل مقارنة تحفظ لقطة، ومنها يُحكم إن كان السعر الحالي
 * منخفضًا أو عاديًا أو مرتفعًا مقارنة بآخر 90 يومًا؛ والمستخدم يتابع منتجًا بسعر مستهدف يُفحص يوميًا.
 */
class PriceWatchController extends Controller
{
    /** GET /market-intel/price-history?q=&countries= */
    public function history(Request $request): JsonResponse
    {
        $d = $request->validate(['q' => ['required', 'string', 'min:2', 'max:160'], 'countries' => ['required', 'string', 'max:60']]);
        $q = Str::lower(trim($d['q']));
        $countries = Countries::valid(explode(',', $d['countries']));
        $rows = DB::table('intel.price_snapshots')->where('query', $q)->whereIn('country', $countries)->where('source', 'all')
            ->where('fetched_at', '>=', now()->subDays(365))->orderBy('fetched_at')->get(['country', 'min_omr', 'median_omr', 'fetched_at']);
        // لقطة = جولة مقارنة واحدة (نفس الساعة)؛ أدنى سعر بين الدول المختارة فيها.
        $rounds = $rows->groupBy(fn ($r) => substr((string) $r->fetched_at, 0, 13))->map(fn ($g) => ['at' => (string) $g->first()->fetched_at, 'min_omr' => (float) $g->min('min_omr'),
            'by_country' => $g->mapWithKeys(fn ($r) => [$r->country => (float) $r->min_omr])->all()])->values();

        return response()->json(['query' => $q, 'rounds' => $rounds, 'insight' => $this->insight($rounds->pluck('min_omr')->all())]);
    }

    /**
     * حكم السعر الحالي مقابل تاريخه (يتطلب 4 جولات فأكثر خلال 90 يومًا).
     *
     * @param  list<float>  $mins
     * @return array{verdict: string, label: string, current: float, low: float, high: float, median: float, n: int}|null
     */
    public function insight(array $mins): ?array
    {
        $window = array_slice($mins, -60);
        if (count($window) < 4) {
            return null;
        }
        $current = end($window);
        $sorted = $window;
        sort($sorted);
        $q = fn (float $p) => $sorted[(int) floor($p * (count($sorted) - 1))];
        [$p25, $p50, $p75] = [$q(0.25), $q(0.5), $q(0.75)];
        $verdict = $current <= $p25 ? 'low' : ($current >= $p75 ? 'high' : 'typical');

        return ['verdict' => $verdict, 'label' => ['low' => 'السعر الآن منخفض مقارنة بتاريخه', 'typical' => 'السعر الآن ضمن المعتاد', 'high' => 'السعر الآن مرتفع مقارنة بتاريخه'][$verdict],
            'current' => $current, 'low' => $sorted[0], 'high' => end($sorted), 'median' => $p50, 'n' => count($window)];
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => DB::table('app.price_watches')->where('user_id', $request->user()->id)->orderByDesc('id')->get()->map(fn ($w) => [...(array) $w, 'last_best' => $w->last_best ? json_decode($w->last_best, true) : null])]);
    }

    public function store(Request $request): JsonResponse
    {
        $d = $request->validate(['q' => ['required', 'string', 'min:2', 'max:160'], 'countries' => ['required', 'string', 'max:60'], 'target_omr' => ['nullable', 'numeric', 'min:0', 'max:1000000']]);
        abort_if(DB::table('app.price_watches')->where('user_id', $request->user()->id)->count() >= 20, 422, 'الحد الأقصى 20 منتجًا في قائمة المتابعة.');
        $countries = Countries::valid(explode(',', $d['countries']));
        sort($countries);
        abort_if($countries === [], 422, 'اختر دولة واحدة على الأقل.');
        DB::table('app.price_watches')->updateOrInsert(
            ['user_id' => $request->user()->id, 'query' => Str::lower(trim($d['q'])), 'countries' => implode(',', $countries)],
            ['target_omr' => $d['target_omr'] ?? null, 'created_at' => now()],
        );

        return $this->index($request);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        DB::table('app.price_watches')->where('id', $id)->where('user_id', $request->user()->id)->delete();

        return $this->index($request);
    }
}
