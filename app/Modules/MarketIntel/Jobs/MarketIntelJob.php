<?php

namespace App\Modules\MarketIntel\Jobs;

use App\Modules\MarketIntel\AiAdvisor;
use App\Modules\MarketIntel\MarketIntelService;
use App\Modules\MarketIntel\OecTradeClient;
use App\Modules\Prices\CurrencyConverter;
use App\Modules\Prices\MarketplaceSearch;
use App\Modules\Prices\PriceComparison;
use App\Modules\Prices\SubscriptionSearch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * مهمة ذكاء الأسواق التي تحتاج الإنترنت (OEC وخدمة الذكاء الاصطناعي): تعمل في عامل الطابور «intel» وتكتب نتيجتها في ذاكرة Redis
 * ليقرأها API المعزول. الفشل يُسجَّل بمفتاح خطأ قصير فيتوقف استطلاع الواجهة بدل الانتظار الأبدي.
 */
final class MarketIntelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 240;

    /** @param  array<string, mixed>  $params */
    public function __construct(public string $kind, public array $params = [])
    {
        $this->onQueue('intel');
    }

    public static function analysisKey(int $product, string $mode): string
    {
        return "mi:analysis:v3:{$product}:{$mode}";
    }

    public static function briefKey(int $product, string $mode): string
    {
        return "mi:briefres:v3:{$product}:{$mode}";
    }

    /** @param  array<string, mixed>  $params */
    public static function resultKey(string $kind, array $params): string
    {
        return "mi:res:v1:{$kind}:".md5(json_encode($params));
    }

    public static function errorKey(string $name): string
    {
        return "mi:error:{$name}";
    }

    public function handle(MarketIntelService $service, OecTradeClient $oec, AiAdvisor $ai): void
    {
        $name = $this->kind.':'.json_encode($this->params);
        try {
            match ($this->kind) {
                'products' => Cache::put('mi:products:ready', count($oec->products()), 30 * 86400),
                'analysis' => $this->analysis($service, $ai),
                'brief' => $this->brief($service, $ai),
                'ask' => $this->ask($service, $ai),
                'prices' => $this->prices(),
                'subs' => Cache::put(self::resultKey('subs', $this->params), (new SubscriptionSearch($ai, new CurrencyConverter))->search((string) $this->params['service'], $this->params['countries']) + ['fetched_at' => now()->toIso8601String()], 24 * 3600),
                'resolve' => $this->resolve($oec, $ai),
                'markets' => Cache::put(self::resultKey('markets', $this->params), (new MarketplaceSearch(new CurrencyConverter))->search((string) $this->params['q']) + ['fetched_at' => now()->toIso8601String()], 3600),
            };
        } catch (Throwable $e) {
            report($e);
            Cache::put(self::errorKey($name), true, 300);
            if ($this->kind === 'ask') {
                Cache::put('mi:answer:'.$this->params['id'], ['error' => 'تعذّر الحصول على إجابة الآن.'], 3600);
            }
        } finally {
            Cache::forget('mi:pending:'.$name);
        }
    }

    private function prices(): void
    {
        $result = PriceComparison::defaultProviders()->compare((string) $this->params['q'], $this->params['countries']);
        PriceComparison::snapshot((string) $this->params['q'], $result);
        Cache::put(self::resultKey('prices', $this->params), $result + ['fetched_at' => now()->toIso8601String()], 3600);
    }

    private function resolve(OecTradeClient $oec, AiAdvisor $ai): void
    {
        $codes = $ai->resolveHs((string) $this->params['text']);
        $byHs = collect($oec->products())->keyBy('hs4');
        $found = array_values(array_filter(array_map(fn ($c) => $byHs->get($c), $codes)));
        Cache::put(self::resultKey('resolve', $this->params), ['data' => $found], 30 * 86400);
    }

    private function analysis(MarketIntelService $service, AiAdvisor $ai): void
    {
        $analysis = $service->analysis((int) $this->params['product'], (string) $this->params['mode']);
        Cache::put(self::analysisKey((int) $this->params['product'], (string) $this->params['mode']), $analysis, 7 * 86400);
        $this->brief($service, $ai, $analysis);
    }

    /** @param  ?array<string, mixed>  $analysis */
    private function brief(MarketIntelService $service, AiAdvisor $ai, ?array $analysis = null): void
    {
        $analysis ??= Cache::get(self::analysisKey((int) $this->params['product'], (string) $this->params['mode'])) ?? $service->analysis((int) $this->params['product'], (string) $this->params['mode']);
        Cache::put(self::briefKey((int) $this->params['product'], (string) $this->params['mode']), ['data' => $ai->brief($service->evidence($analysis)), 'ai_enabled' => $ai->enabled()], 6 * 3600);
    }

    private function ask(MarketIntelService $service, AiAdvisor $ai): void
    {
        $analysis = Cache::get(self::analysisKey((int) $this->params['product'], (string) $this->params['mode'])) ?? $service->analysis((int) $this->params['product'], (string) $this->params['mode']);
        Cache::put('mi:answer:'.$this->params['id'], ['answer' => $ai->ask($service->evidence($analysis), (string) $this->params['question'])], 3600);
    }
}
