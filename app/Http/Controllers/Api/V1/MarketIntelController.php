<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Support\Secrets;
use App\Http\Controllers\Controller;
use App\Modules\MarketIntel\IntelReference;
use App\Modules\MarketIntel\Jobs\MarketIntelJob;
use App\Modules\MarketIntel\ProductSearch;
use App\Modules\Prices\Countries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * ذكاء الأسواق: اختيار المنتجات والموردين والأسواق والأسعار بتقييم حتمي من بيانات BACI والمخاطر، وتوصية بالذكاء الاصطناعي فوقه.
 * حاوية الواجهة بلا إنترنت صادر عمدًا، فكل ما يحتاج OEC أو خدمة الذكاء الاصطناعي يُنفَّذ في عامل الطابور «intel» وتُقرأ نتيجته من Redis:
 * أول طلب يردّ 202 «قيد الإعداد» وتعيد الواجهة السؤال حتى تجهز النتيجة (7 أيام تخزين للتحليل، 6 ساعات للتوصية).
 */
class MarketIntelController extends Controller
{
    /** GET /market-intel/products?q=cars — بحث في منتجات HS4 بالعربية أو الإنجليزية أو الرمز. */
    public function products(Request $request): JsonResponse
    {
        $q = Str::lower(trim((string) ($request->validate(['q' => ['nullable', 'string', 'max:80']])['q'] ?? '')));
        $all = Cache::get('mi:products:v2');
        if (! is_array($all)) {
            return $this->pending('products', []);
        }

        return response()->json(['data' => (new ProductSearch)->search($all, $q, 20)]);
    }

    /** GET /market-intel/catalog — شجرة المنتجات (أقسام ← فصول ← منتجات HS4) للقوائم المنسدلة. */
    public function catalog(): JsonResponse
    {
        $all = Cache::get('mi:products:v2');
        if (! is_array($all)) {
            return $this->pending('products', []);
        }

        return response()->json(Cache::remember('mi:catalog:v1:'.count($all), 86400, fn () => IntelReference::catalog($all)));
    }

    /** GET /market-intel/analysis?product=52709&mode=import */
    public function analysis(Request $request): JsonResponse
    {
        [$product, $mode] = $this->params($request);
        $hit = Cache::get(MarketIntelJob::analysisKey($product, $mode));

        return is_array($hit) ? response()->json($hit) : $this->pending('analysis', ['product' => $product, 'mode' => $mode]);
    }

    /** GET /market-intel/brief?product=&mode= — توصية الذكاء الاصطناعي (أو التحليل الآلي عند غياب المفتاح). */
    public function brief(Request $request): JsonResponse
    {
        [$product, $mode] = $this->params($request);
        $hit = Cache::get(MarketIntelJob::briefKey($product, $mode));
        if (is_array($hit)) {
            return response()->json($hit);
        }

        return $this->pending(Cache::has(MarketIntelJob::analysisKey($product, $mode)) ? 'brief' : 'analysis', ['product' => $product, 'mode' => $mode]);
    }

    /** POST /market-intel/ask — سؤال حر عن المنتج (يتطلب تسجيل الدخول لأنه يستهلك رصيد الذكاء الاصطناعي). */
    public function ask(Request $request): JsonResponse
    {
        $data = $request->validate(['product' => ['required', 'integer', 'min:10000', 'max:999999'], 'mode' => ['required', 'in:import,export'], 'question' => ['required', 'string', 'min:5', 'max:500']]);
        if (! Secrets::has('anthropic_api_key')) {
            return response()->json(['message' => 'الذكاء الاصطناعي غير مفعَّل على الخادم (لم يُضبط ANTHROPIC_API_KEY).'], 503);
        }
        $id = (string) Str::uuid();
        MarketIntelJob::dispatch('ask', ['id' => $id, 'product' => (int) $data['product'], 'mode' => $data['mode'], 'question' => $data['question']]);

        return response()->json(['id' => $id], 202);
    }

    /** GET /market-intel/answer?id= */
    public function answer(Request $request): JsonResponse
    {
        $id = (string) $request->validate(['id' => ['required', 'uuid']])['id'];
        $hit = Cache::get("mi:answer:{$id}");
        if (! is_array($hit)) {
            return response()->json(['status' => 'pending'], 202);
        }

        return isset($hit['error']) ? response()->json(['message' => $hit['error']], 502) : response()->json($hit);
    }

    /** GET /market-intel/capabilities — ما المفعَّل على الخادم (مفاتيح الذكاء الاصطناعي وأسعار المتاجر) وقائمة الدول. */
    public function capabilities(): JsonResponse
    {
        return response()->json([
            'ai' => Secrets::has('anthropic_api_key'), 'shopping' => Secrets::has('serpapi_key'),
            'sources' => [['key' => 'google', 'label' => 'Google Shopping (SerpAPI)', 'free' => false, 'enabled' => Secrets::has('serpapi_key')], ['key' => 'apple', 'label' => 'متجر آبل', 'free' => true, 'enabled' => true], ['key' => 'steam', 'label' => 'Steam', 'free' => true, 'enabled' => true], ['key' => 'openprices', 'label' => 'Open Prices (بقالة)', 'free' => true, 'enabled' => true]],
            'countries' => collect(Countries::ALL)->map(fn ($v, $k) => ['code' => $k, 'name_ar' => $v[0], 'currency' => $v[1]])->values(),
        ]);
    }

    /** GET /market-intel/prices?q=iphone 15&countries=OM,AE,SA — عروض حية بالريال العُماني من مصادر مجانية (آبل، Steam، Open Prices) وGoogle Shopping إن ضُبط مفتاحه. */
    public function prices(Request $request): JsonResponse
    {
        $d = $request->validate(['q' => ['required', 'string', 'min:2', 'max:120'], 'countries' => ['required', 'string', 'max:60']]);

        return $this->cached('prices', ['q' => Str::lower(trim($d['q'])), 'countries' => $this->countries($d['countries'])]);
    }

    /** GET /market-intel/marketplaces?q=iphone 15 — المنتج في أمازون وإيباي ووولمارت والمتاجر الصينية والخليجية (يتطلب مفتاح SerpAPI). */
    public function marketplaces(Request $request): JsonResponse
    {
        $d = $request->validate(['q' => ['required', 'string', 'min:2', 'max:120']]);
        if (! Secrets::has('serpapi_key')) {
            return response()->json(['message' => 'مقارنة المتاجر الإلكترونية (أمازون والمتاجر الصينية…) تتطلب مفتاح SerpAPI من صفحة الإعدادات.'], 503);
        }

        return $this->cached('markets', ['q' => Str::lower(trim($d['q']))]);
    }

    /** GET /market-intel/subscriptions?service=Netflix&countries=OM,AE — أسعار اشتراكات بالذكاء الاصطناعي من صفحات المزوّد العامة. */
    public function subscriptions(Request $request): JsonResponse
    {
        $d = $request->validate(['service' => ['required', 'string', 'min:2', 'max:120'], 'countries' => ['required', 'string', 'max:60']]);
        if (! Secrets::has('anthropic_api_key')) {
            return response()->json(['message' => 'أسعار الاشتراكات تتطلب تفعيل الذكاء الاصطناعي (ANTHROPIC_API_KEY).'], 503);
        }

        return $this->cached('subs', ['service' => trim($d['service']), 'countries' => $this->countries($d['countries'])]);
    }

    /** GET /market-intel/resolve?text=حليب أطفال — أي منتج بوصف حر إلى رموز HS4 (بالذكاء الاصطناعي). */
    public function resolve(Request $request): JsonResponse
    {
        $d = $request->validate(['text' => ['required', 'string', 'min:2', 'max:120']]);
        if (! Secrets::has('anthropic_api_key')) {
            return response()->json(['message' => 'تحويل الوصف الحر إلى رمز HS يتطلب تفعيل الذكاء الاصطناعي.'], 503);
        }

        return $this->cached('resolve', ['text' => trim($d['text'])]);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function cached(string $kind, array $params): JsonResponse
    {
        if (isset($params['countries']) && $params['countries'] === []) {
            return response()->json(['message' => 'اختر دولة واحدة على الأقل.'], 422);
        }
        $hit = Cache::get(MarketIntelJob::resultKey($kind, $params));

        return is_array($hit) ? response()->json($hit) : $this->pending($kind, $params);
    }

    /** @return list<string> */
    private function countries(string $csv): array
    {
        $c = Countries::valid(explode(',', $csv));
        sort($c);

        return $c;
    }

    /** @return array{0: int, 1: 'import'|'export'} */
    private function params(Request $request): array
    {
        $d = $request->validate(['product' => ['required', 'integer', 'min:10000', 'max:999999'], 'mode' => ['nullable', 'in:import,export']]);

        return [(int) $d['product'], $d['mode'] ?? 'import'];
    }

    /** @param  array<string, mixed>  $params */
    private function pending(string $kind, array $params): JsonResponse
    {
        $name = $kind.':'.json_encode($params);
        if (Cache::has(MarketIntelJob::errorKey($name))) {
            return response()->json(['message' => 'تعذّر جلب البيانات من المصدر الآن؛ حاول بعد دقائق.'], 502);
        }
        if (Cache::add('mi:pending:'.$name, 1, 240)) {
            MarketIntelJob::dispatch($kind, $params);
        }

        return response()->json(['status' => 'pending'], 202);
    }
}
