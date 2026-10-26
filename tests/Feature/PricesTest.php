<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\MarketIntel\AiAdvisor;
use App\Modules\MarketIntel\Jobs\MarketIntelJob;
use App\Modules\MarketIntel\MarketIntelService;
use App\Modules\MarketIntel\OecTradeClient;
use App\Modules\Prices\AppleStoreProvider;
use App\Modules\Prices\CurrencyConverter;
use App\Modules\Prices\OpenPricesProvider;
use App\Modules\Prices\PriceComparison;
use App\Modules\Prices\SerpApiShoppingProvider;
use App\Modules\Prices\SteamProvider;
use App\Modules\Prices\SubscriptionSearch;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\DatabaseTestCase;

class PricesTest extends DatabaseTestCase
{
    private function user(): User
    {
        return User::create(['name' => 'مستخدم', 'email' => uniqid('p').'@example.com', 'password' => 'correct-horse-battery', 'is_admin' => false]);
    }

    private function fakeFx(): void
    {
        Cache::flush();
        Http::fake(['open.er-api.com/*' => Http::response(['rates' => ['USD' => 1, 'EUR' => 0.9, 'INR' => 83.0, 'EGP' => 50.0]])]);
    }

    public function test_converter_uses_pegs_and_live_rates(): void
    {
        $this->fakeFx();
        $fx = new CurrencyConverter;

        $this->assertEqualsWithDelta(0.3845, $fx->toOmr(1.0, 'USD'), 0.0001);
        $this->assertEqualsWithDelta(0.3845, $fx->toOmr(3.6725, 'AED'), 0.0001, 'ربط الدرهم');
        $this->assertEqualsWithDelta(0.3845 / 0.9, $fx->toOmr(1.0, 'EUR'), 0.0001);
        $this->assertNull($fx->toOmr(1.0, 'XXX'));
    }

    public function test_shopping_provider_parses_offers_and_detects_currency(): void
    {
        Cache::flush();
        config(['omanilab.prices.serpapi_key' => 'k']);
        Http::fake(['serpapi.com/*' => Http::response(['shopping_results' => [
            ['title' => 'Phone A', 'source' => 'Noon', 'price' => 'د.إ 3,299', 'extracted_price' => 3299, 'product_link' => 'https://example.com/a', 'rating' => 4.5, 'reviews' => 120],
            ['title' => 'Phone B', 'source' => 'Store', 'price' => '$799', 'extracted_price' => 799, 'link' => 'http://insecure.example/b'],
            ['title' => 'Bad', 'price' => 'free', 'extracted_price' => 0],
        ]])]);
        $p = new SerpApiShoppingProvider;
        $offers = $p->search('phone', 'AE');

        $this->assertTrue($p->configured());
        $this->assertCount(2, $offers);
        $this->assertSame('AED', $offers[0]['currency']);
        $this->assertSame('USD', $offers[1]['currency']);
        $this->assertNull($offers[1]['link'], 'روابط http غير الآمنة تُسقَط');
    }

    public function test_comparison_ranks_by_omr_and_summarises_by_country(): void
    {
        $this->fakeFx();
        config(['omanilab.prices.serpapi_key' => 'k']);
        Http::fake([
            'open.er-api.com/*' => Http::response(['rates' => ['USD' => 1]]),
            'serpapi.com/*' => fn ($req) => Http::response(['shopping_results' => str_contains($req->url(), 'gl=ae')
                ? [['title' => 'A', 'source' => 'S1', 'price' => 'AED 3.6725', 'extracted_price' => 3.6725], ['title' => 'A2', 'source' => 'S2', 'price' => 'AED 7.345', 'extracted_price' => 7.345]]
                : [['title' => 'B', 'source' => 'S3', 'price' => 'ر.ع 1.0', 'extracted_price' => 1.0]]]),
        ]);
        $r = (new PriceComparison([new SerpApiShoppingProvider], new CurrencyConverter))->compare('x', ['AE', 'OM']);

        $this->assertCount(3, $r['offers']);
        $this->assertSame('A', $r['offers'][0]['title'], 'الأرخص أولًا: 3.6725 درهمًا = 0.3845 ر.ع');
        $this->assertEqualsWithDelta(1.0, $r['summary']['oman_median'], 0.001);
        $this->assertSame('AE', $r['by_country'][0]['country'], 'الإمارات أرخص بوسيط 0.577 ر.ع');
        $this->assertEqualsWithDelta(0.3845, $r['summary']['min'], 0.001);
        $this->assertSame([], $r['errors']);
    }

    public function test_subscription_rows_without_https_url_or_valid_price_are_rejected(): void
    {
        $this->fakeFx();
        $s = new SubscriptionSearch(new AiAdvisor, new CurrencyConverter);
        $rows = $s->clean([
            ['country' => 'AE', 'plan' => 'Standard', 'price' => 49, 'currency' => 'AED', 'period' => 'month', 'url' => 'https://netflix.example/ae', 'as_of' => '2026-10-01', 'notes' => null],
            ['country' => 'AE', 'plan' => 'NoUrl', 'price' => 49, 'currency' => 'AED', 'period' => 'month', 'url' => 'http://x', 'as_of' => null],
            ['country' => 'AE', 'plan' => 'BadPrice', 'price' => 'abc', 'currency' => 'AED', 'period' => 'month', 'url' => 'https://x'],
            ['country' => 'ZZ', 'plan' => 'Other', 'price' => 5, 'currency' => 'USD', 'period' => 'month', 'url' => 'https://x'],
            ['country' => 'IN', 'plan' => 'Yearly', 'price' => 830, 'currency' => 'INR', 'period' => 'year', 'url' => 'https://y', 'as_of' => 'اليوم'],
        ], ['AE', 'IN']);

        $this->assertCount(2, $rows);
        $this->assertSame('Yearly', $rows[0]['plan'], 'الأرخص شهريًا أولًا');
        $this->assertEqualsWithDelta(0.32, $rows[0]['omr_monthly'], 0.01, '830 روبية سنويًا = 3.845 ر.ع ÷ 12');
        $this->assertNull($rows[0]['as_of'], 'تاريخ غير صالح يُهمَل');
        $this->assertSame('2026-10-01', $rows[1]['as_of']);
    }

    public function test_price_endpoints_require_login_and_keys(): void
    {
        Cache::flush();
        $this->getJson('/api/v1/market-intel/prices?q=phone&countries=OM')->assertUnauthorized();

        $this->actingAs($this->user());
        config(['omanilab.prices.serpapi_key' => null, 'omanilab.ai.key' => null]);
        $this->getJson('/api/v1/market-intel/subscriptions?service=Netflix&countries=OM')->assertStatus(503);
        $this->getJson('/api/v1/market-intel/resolve?text=حليب')->assertStatus(503);
    }

    public function test_relevance_drops_unrelated_titles(): void
    {
        $this->assertSame(1.0, PriceComparison::relevance('Minecraft Standard Edition PC', 'Minecraft: Java & Bedrock'));
        $this->assertSame(0.0, PriceComparison::relevance('Minecraft Standard Edition PC', 'Dilwale Dulhania Le Jayenge'));
        $this->assertEqualsWithDelta(2 / 3, PriceComparison::relevance('iPhone 15 case', 'iPhone 15 Pro'), 0.001);
    }

    public function test_free_providers_parse_apple_steam_and_open_prices(): void
    {
        Cache::flush();
        Http::fake([
            'itunes.apple.com/*' => Http::response(['results' => [['trackName' => 'App X', 'price' => 4.99, 'currency' => 'OMR', 'trackViewUrl' => 'https://apps.apple.com/om/app/x', 'sellerName' => 'Dev'], ['trackName' => 'Free', 'price' => 0, 'currency' => 'OMR']]]),
            'store.steampowered.com/*' => Http::response(['items' => [['name' => 'Game', 'id' => 10, 'price' => ['currency' => 'USD', 'final' => 1999]], ['name' => 'Free game', 'id' => 11, 'price' => null]]]),
            'prices.openfoodfacts.org/api/v1/products*' => Http::response(['items' => [['code' => '123', 'product_name' => 'Milk', 'price_count' => 5]]]),
            'prices.openfoodfacts.org/api/v1/prices*' => Http::response(['items' => [
                ['price' => 1.2, 'currency' => 'EUR', 'date' => '2026-10-01', 'location' => ['osm_address_country_code' => 'FR', 'osm_name' => 'Carrefour']],
                ['price' => 1.1, 'currency' => 'EUR', 'date' => '2026-09-01', 'location' => ['osm_address_country_code' => 'FR', 'osm_name' => 'Carrefour']],
                ['price' => 0.9, 'currency' => 'EUR', 'date' => '2026-09-01', 'location' => ['osm_address_country_code' => 'DE', 'osm_name' => 'Aldi']],
            ]]),
        ]);

        $apple = (new AppleStoreProvider)->search('x', 'OM');
        $steam = (new SteamProvider)->search('game', 'AE');
        $op = (new OpenPricesProvider)->search('milk', 'FR');

        $this->assertCount(1, $apple);
        $this->assertSame('OMR', $apple[0]['currency']);
        $this->assertCount(1, $steam);
        $this->assertEqualsWithDelta(19.99, $steam[0]['price'], 0.001);
        $this->assertCount(1, $op, 'آخر سعر لكل متجر فقط وبلد واحد');
        $this->assertSame(1.2, $op[0]['price']);
    }

    public function test_prices_work_without_any_key_using_free_sources(): void
    {
        Cache::flush();
        Queue::fake();
        config(['omanilab.prices.serpapi_key' => null]);
        $this->actingAs($this->user());

        $this->getJson('/api/v1/market-intel/prices?q=minecraft&countries=OM')->assertStatus(202);
        Queue::assertPushed(MarketIntelJob::class);
    }

    public function test_prices_first_request_dispatches_job_with_sorted_valid_countries(): void
    {
        Cache::flush();
        Queue::fake();
        config(['omanilab.prices.serpapi_key' => 'k']);
        $this->actingAs($this->user());

        $this->getJson('/api/v1/market-intel/prices?q=iPhone 15&countries=AE,OM,XX')->assertStatus(202);
        $this->getJson('/api/v1/market-intel/prices?q=iPhone 15&countries=XX')->assertStatus(422);

        Queue::assertPushed(MarketIntelJob::class, fn ($j) => $j->kind === 'prices' && $j->params['countries'] === ['AE', 'OM'] && $j->params['q'] === 'iphone 15');
    }

    public function test_capabilities_is_public_and_reports_flags(): void
    {
        config(['omanilab.prices.serpapi_key' => null, 'omanilab.ai.key' => 'x']);

        $this->getJson('/api/v1/market-intel/capabilities')->assertOk()->assertJsonPath('ai', true)->assertJsonPath('shopping', false)->assertJsonPath('countries.0.code', 'OM');
    }

    public function test_resolve_job_maps_ai_codes_to_known_products_only(): void
    {
        Cache::flush();
        config(['omanilab.ai.key' => 'k']);
        Cache::put('mi:products:v2', [['id' => 31901, 'hs4' => '1901', 'name_en' => 'Dough', 'name_ar' => 'x']]);
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => '{"hs4":["1901","9999"]}']]])]);
        (new MarketIntelJob('resolve', ['text' => 'حليب أطفال']))->handle(app(MarketIntelService::class), app(OecTradeClient::class), new AiAdvisor);

        $hit = Cache::get(MarketIntelJob::resultKey('resolve', ['text' => 'حليب أطفال']));
        $this->assertCount(1, $hit['data']);
        $this->assertSame('1901', $hit['data'][0]['hs4']);
    }
}
