<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\V1\PriceWatchController;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\DatabaseTestCase;

class PriceWatchTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::unprepared('TRUNCATE intel.price_snapshots, app.price_watches RESTART IDENTITY CASCADE');
    }

    private function user(): User
    {
        return User::create(['name' => 'م', 'email' => uniqid('w').'@example.com', 'password' => 'correct-horse-battery']);
    }

    public function test_insight_classifies_current_price_against_history(): void
    {
        $c = new PriceWatchController;
        $this->assertNull($c->insight([10, 11, 12]), 'أقل من 4 جولات: لا حكم');
        $this->assertSame('low', $c->insight([12, 11, 13, 12, 9])['verdict']);
        $this->assertSame('high', $c->insight([10, 11, 10, 9, 14])['verdict']);
        $this->assertSame('typical', $c->insight([10, 12, 9, 13, 11])['verdict']);
    }

    public function test_history_groups_rounds_and_min_across_countries(): void
    {
        foreach ([['2026-09-01 10:00', 'OM', 12.0], ['2026-09-01 10:00', 'AE', 10.0], ['2026-09-08 10:00', 'OM', 11.0], ['2026-09-08 10:00', 'AE', 11.5]] as [$at, $c, $min]) {
            DB::table('intel.price_snapshots')->insert(['query' => 'game x', 'country' => $c, 'source' => 'all', 'n_offers' => 3, 'min_omr' => $min, 'median_omr' => $min + 1, 'fetched_at' => now()->setDateTimeFrom(new \DateTimeImmutable($at))]);
        }
        $res = $this->actingAs($this->user())->getJson('/api/v1/market-intel/price-history?q=Game X&countries=OM,AE')->assertOk();
        $this->assertEquals([10.0, 11.0], array_column($res->json('rounds'), 'min_omr'));
    }

    public function test_watchlist_is_private_per_user_and_capped(): void
    {
        $a = $this->user();
        $b = $this->user();
        $this->actingAs($a)->postJson('/api/v1/market-intel/watches', ['q' => 'Minecraft', 'countries' => 'OM,AE', 'target_omr' => 5])->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($b)->getJson('/api/v1/market-intel/watches')->assertJsonCount(0, 'data');
        $id = DB::table('app.price_watches')->value('id');
        $this->actingAs($b)->deleteJson("/api/v1/market-intel/watches/{$id}")->assertOk();
        $this->assertSame(1, DB::table('app.price_watches')->count(), 'لا يحذف متابعة غيره');
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/market-intel/watches')->assertUnauthorized();
    }

    public function test_watch_command_snapshots_and_flags_target_reached(): void
    {
        Cache::flush();
        config(['omanilab.prices.serpapi_key' => null]);
        Http::fake([
            'open.er-api.com/*' => Http::response(['rates' => ['USD' => 1]]),
            'itunes.apple.com/*' => Http::response(['results' => [['trackName' => 'Game X', 'price' => 4.0, 'currency' => 'OMR', 'trackViewUrl' => 'https://apps.apple.com/x']]]),
            'store.steampowered.com/*' => Http::response(['items' => []]),
            'prices.openfoodfacts.org/*' => Http::response(['items' => []]),
        ]);
        $u = $this->user();
        DB::table('app.price_watches')->insert(['user_id' => $u->id, 'query' => 'game x', 'countries' => 'OM', 'target_omr' => 5, 'created_at' => now()]);
        $this->artisan('prices:watch')->assertSuccessful();

        $w = DB::table('app.price_watches')->first();
        $this->assertEquals(4.0, $w->last_min_omr);
        $this->assertNotNull($w->reached_at);
        $this->assertSame(2, DB::table('intel.price_snapshots')->where('query', 'game x')->count(), 'لقطة للمصدر وأخرى مجمّعة');
    }
}
