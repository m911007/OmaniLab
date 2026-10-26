<?php

namespace Tests\Feature;

use App\Modules\MarketIntel\AiAdvisor;
use App\Modules\MarketIntel\Jobs\MarketIntelJob;
use App\Modules\MarketIntel\MarketIntelService;
use App\Modules\MarketIntel\OecTradeClient;
use App\Modules\MarketIntel\SupplierScorer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\DatabaseTestCase;

class MarketIntelTest extends DatabaseTestCase
{
    /** @return list<array{iso: string, name: string, year: int, value: float, qty: ?float}> */
    private function rows(): array
    {
        $r = [];
        foreach ([2022, 2023] as $y) {
            $r[] = ['iso' => 'JPN', 'name' => 'Japan', 'year' => $y, 'value' => $y === 2023 ? 600.0 : 500.0, 'qty' => 20.0]; // 30/طن
            $r[] = ['iso' => 'CHN', 'name' => 'China', 'year' => $y, 'value' => $y === 2023 ? 900.0 : 600.0, 'qty' => 60.0]; // 15/طن
            $r[] = ['iso' => 'DEU', 'name' => 'Germany', 'year' => $y, 'value' => 300.0, 'qty' => null];
            $r[] = ['iso' => 'OMN', 'name' => 'Oman', 'year' => $y, 'value' => 50.0, 'qty' => 1.0];
        }

        return $r;
    }

    public function test_import_mode_prefers_cheaper_supplier_and_excludes_oman(): void
    {
        $risk = ['JPN' => ['lpi' => 4.0, 'stability' => 1.0, 'inform' => 2.0], 'CHN' => ['lpi' => 3.8, 'stability' => 0.0, 'inform' => 3.0]];
        $out = (new SupplierScorer)->score($this->rows(), [['iso' => 'JPN', 'name' => 'Japan', 'year' => 2023, 'value' => 100.0, 'qty' => 2.0]], $risk, 'import', 2023);
        $byIso = collect($out['candidates'])->keyBy('iso');

        $this->assertFalse($byIso->has('OMN'));
        $this->assertGreaterThan($byIso['JPN']['components']['price'], $byIso['CHN']['components']['price']);
        $this->assertNull($byIso['DEU']['components']['price'], 'بلا كمية لا سعر وحدة');
        $this->assertNotNull($byIso['DEU']['score'], 'التقييم لا يعاقب نقص البيانات');
        $this->assertEqualsWithDelta(50.0, $out['summary']['oman_unit_value'], 0.01);
        $this->assertGreaterThan(0, $out['summary']['saving_estimate']);
        $this->assertEqualsWithDelta(100.0, $byIso['JPN']['oman_value'], 0.01);
    }

    public function test_export_mode_prefers_higher_price_paid(): void
    {
        $out = (new SupplierScorer)->score($this->rows(), [], [], 'export', 2023);
        $byIso = collect($out['candidates'])->keyBy('iso');

        $this->assertGreaterThan($byIso['CHN']['components']['price'], $byIso['JPN']['components']['price']);
    }

    public function test_composite_renormalises_over_missing_components(): void
    {
        $s = new SupplierScorer;

        $this->assertSame(80.0, $s->composite(['scale' => 80.0, 'price' => null], ['scale' => 0.5, 'price' => 0.5]));
        $this->assertNull($s->composite(['scale' => null], ['scale' => 1.0]));
    }

    public function test_brief_falls_back_to_rules_without_key(): void
    {
        config(['omanilab.ai.key' => null]);
        $ev = ['product' => ['name_ar' => 'سيارات'], 'mode' => 'import', 'summary' => ['hhi_oman' => 4000, 'saving_estimate' => 1000000.0], 'candidates' => [['name_ar' => 'اليابان', 'score' => 70]]];
        $b = (new AiAdvisor)->brief($ev);

        $this->assertSame('rules', $b['source']);
        $this->assertNull($b['model']);
        $this->assertNotEmpty($b['recommendations']);
    }

    public function test_brief_uses_ai_when_key_is_set_and_parses_json(): void
    {
        Cache::flush();
        config(['omanilab.ai.key' => 'test-key']);
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => "```json\n{\"summary\":\"ملخص\",\"recommendations\":[\"أ\"],\"risks\":[\"ب\"],\"next_steps\":[\"ج\"]}\n```"]]])]);
        $b = (new AiAdvisor)->brief(['product' => ['name_ar' => 'x'], 'mode' => 'import', 'summary' => [], 'candidates' => []]);

        $this->assertSame('ai', $b['source']);
        $this->assertSame(['أ'], $b['recommendations']);
    }

    public function test_brief_falls_back_when_ai_service_fails(): void
    {
        Cache::flush();
        config(['omanilab.ai.key' => 'test-key']);
        Http::fake(['api.anthropic.com/*' => Http::response('boom', 500)]);
        $b = (new AiAdvisor)->brief(['product' => ['name_ar' => 'x'], 'mode' => 'import', 'summary' => [], 'candidates' => []]);

        $this->assertSame('rules', $b['source']);
    }

    public function test_ask_requires_login(): void
    {
        $this->postJson('/api/v1/market-intel/ask', ['product' => 52709, 'mode' => 'import', 'question' => 'من الأرخص؟'])->assertUnauthorized();
    }

    public function test_products_search_reads_cached_list_and_matches_arabic_name_and_code(): void
    {
        Cache::flush();
        Cache::put('mi:products:v2', [['id' => 52709, 'hs4' => '2709', 'name_en' => 'Crude Petroleum', 'name_ar' => 'النفط الخام'], ['id' => 178703, 'hs4' => '8703', 'name_en' => 'Cars', 'name_ar' => 'سيارات']]);

        $this->getJson('/api/v1/market-intel/products?q=سيار')->assertOk()->assertJsonPath('data.0.hs4', '8703');
        $this->getJson('/api/v1/market-intel/products?q=2709')->assertOk()->assertJsonPath('data.0.name_en', 'Crude Petroleum');
    }

    public function test_first_request_is_pending_and_dispatches_one_job(): void
    {
        Cache::flush();
        Queue::fake();

        $this->getJson('/api/v1/market-intel/analysis?product=52709&mode=import')->assertStatus(202)->assertJsonPath('status', 'pending');
        $this->getJson('/api/v1/market-intel/analysis?product=52709&mode=import')->assertStatus(202);

        Queue::assertPushed(MarketIntelJob::class, 1);
        Queue::assertPushedOn('intel', MarketIntelJob::class);
    }

    public function test_cached_analysis_is_served_without_dispatch(): void
    {
        Cache::flush();
        Queue::fake();
        Cache::put(MarketIntelJob::analysisKey(52709, 'import'), ['candidates' => [], 'summary' => ['mode' => 'import']]);

        $this->getJson('/api/v1/market-intel/analysis?product=52709&mode=import')->assertOk()->assertJsonPath('summary.mode', 'import');
        Queue::assertNothingPushed();
    }

    public function test_job_failure_surfaces_as_502_instead_of_endless_polling(): void
    {
        Cache::flush();
        Queue::fake();
        Cache::put(MarketIntelJob::errorKey('analysis:'.json_encode(['product' => 52709, 'mode' => 'import'])), true, 300);

        $this->getJson('/api/v1/market-intel/analysis?product=52709&mode=import')->assertStatus(502);
    }

    public function test_job_stores_analysis_and_brief(): void
    {
        Cache::flush();
        config(['omanilab.ai.key' => null]);
        $service = \Mockery::mock(MarketIntelService::class);
        $service->shouldReceive('analysis')->once()->andReturn(['product' => ['name_ar' => 'x'], 'summary' => ['mode' => 'import'], 'candidates' => []]);
        $service->shouldReceive('evidence')->andReturn(['product' => ['name_ar' => 'x'], 'mode' => 'import', 'summary' => [], 'candidates' => []]);

        (new MarketIntelJob('analysis', ['product' => 52709, 'mode' => 'import']))->handle($service, app(OecTradeClient::class), new AiAdvisor);

        $this->assertNotNull(Cache::get(MarketIntelJob::analysisKey(52709, 'import')));
        $this->assertSame('rules', Cache::get(MarketIntelJob::briefKey(52709, 'import'))['data']['source']);
    }
}
