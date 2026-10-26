<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\MarketIntel\ComtradeClient;
use App\Modules\Prices\CurrencyConverter;
use App\Modules\Prices\MarketplaceSearch;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\DatabaseTestCase;

class UnitPricesTest extends DatabaseTestCase
{
    public function test_comtrade_unit_prices_per_item_by_partner_world_and_hs6(): void
    {
        Cache::flush();
        $row = fn (int $rep, int $partner, string $cmd, float $value, float $qty, int $unit = 5) => ['reporterCode' => $rep, 'partnerCode' => $partner, 'cmdCode' => $cmd, 'primaryValue' => $value, 'qty' => $qty, 'qtyUnitCode' => $unit, 'netWgt' => $qty * 1500, 'customsCode' => 'C00', 'motCode' => 0, 'partner2Code' => 0, 'isQtyEstimated' => false];
        Http::fake([
            '*partnerAreas.json' => Http::response(['results' => [['PartnerCode' => 392, 'PartnerCodeIsoAlpha3' => 'JPN', 'isGroup' => false], ['PartnerCode' => 156, 'PartnerCodeIsoAlpha3' => 'CHN', 'isGroup' => false], ['PartnerCode' => 276, 'PartnerCodeIsoAlpha3' => 'DEU', 'isGroup' => false]]]),
            '*HS.json' => Http::response(['results' => [['id' => '870321', 'text' => '870321 - Small cars'], ['id' => '870322', 'text' => '870322 - Mid cars']]]),
            'comtradeapi.un.org/public/*' => function ($req) use ($row) {
                parse_str((string) parse_url($req->url(), PHP_URL_QUERY), $q);
                if (str_contains((string) ($q['cmdCode'] ?? ''), ',')) {
                    return Http::response(['data' => [$row(512, 0, '870321', 100.0, 10.0), $row(512, 0, '870322', 900.0, 30.0)]]);
                }
                if (($q['reporterCode'] ?? null) == 512) {
                    return Http::response(['data' => [$row(512, 0, '8703', 1000.0, 40.0), $row(512, 392, '8703', 600.0, 20.0), $row(512, 156, '8703', 400.0, 20.0)]]);
                }

                return Http::response(['data' => [$row(392, 0, '8703', 3000.0, 100.0), $row(156, 0, '8703', 1500.0, 100.0), $row(276, 0, '8703', 4000.0, 100.0)]]);
            },
        ]);
        $u = (new ComtradeClient)->unitPrices('8703', 'import', 2024);

        $this->assertSame('قطعة', $u['unit']);
        $this->assertEqualsWithDelta(25.0, $u['oman_total']['unit_price'], 0.001, '1000 ÷ 40 قطعة');
        $this->assertSame('JPN', $u['oman_by_partner'][0]['iso']);
        $this->assertEqualsWithDelta(30.0, $u['oman_by_partner'][0]['unit_price'], 0.001);
        $this->assertEqualsWithDelta(1500.0, $u['oman_by_partner'][0]['kg_per_unit'], 0.001);
        $this->assertEqualsWithDelta(30.0, $u['world_median_unit_price'], 0.001, 'وسيط 15 و30 و40');
        $this->assertSame('870322', $u['hs6'][0]['code']);
        $this->assertSame('Mid cars', $u['hs6'][0]['text']);
    }

    public function test_marketplace_groups_amazon_chinese_and_gulf_offers(): void
    {
        Cache::flush();
        config(['omanilab.prices.serpapi_key' => 'k']);
        Http::fake([
            'open.er-api.com/*' => Http::response(['rates' => ['USD' => 1, 'GBP' => 0.8]]),
            'serpapi.com/*' => function ($req) {
                parse_str((string) parse_url($req->url(), PHP_URL_QUERY), $q);

                return Http::response(match ($q['engine']) {
                    'amazon' => ['organic_results' => [['title' => 'Phone A', 'extracted_price' => 100, 'link' => 'https://amazon.example/a']]],
                    'google_shopping' => ['shopping_results' => [['title' => 'Phone B', 'source' => 'AliExpress.com', 'extracted_price' => 40], ['title' => 'Phone C', 'source' => 'noon', 'extracted_price' => 90]]],
                    default => [],
                });
            },
        ]);
        $r = (new MarketplaceSearch(new CurrencyConverter))->search('phone');
        $groups = collect($r['groups'])->keyBy('group');

        $this->assertTrue($groups->has('amazon'));
        $this->assertSame(4, $groups['amazon']['count'], 'أربعة نطاقات أمازون');
        $this->assertSame('AliExpress', $r['offers'][0]['platform'], 'الأرخص أولًا');
        $this->assertSame('المتاجر الصينية', $groups['china']['label']);
        $this->assertTrue($groups->has('gulf'));
        $this->assertSame(['china', 'gulf', 'amazon'], array_slice(array_column($r['groups'], 'group'), 0, 3));
    }

    public function test_marketplaces_endpoint_requires_login_and_key(): void
    {
        $this->getJson('/api/v1/market-intel/marketplaces?q=phone')->assertUnauthorized();
        config(['omanilab.prices.serpapi_key' => null]);
        $this->actingAs(User::create(['name' => 'م', 'email' => uniqid('m').'@example.com', 'password' => 'correct-horse-battery', 'is_admin' => false]));
        $this->getJson('/api/v1/market-intel/marketplaces?q=phone')->assertStatus(503);
    }
}
