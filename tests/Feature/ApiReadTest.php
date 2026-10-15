<?php

namespace Tests\Feature;

use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\IngestRunner;
use App\Core\Ingestion\Promoter;
use App\Models\Dataset;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\DatabaseTestCase;
use Tests\Support\FakeConnector;

class ApiReadTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['omanilab.connectors' => [FakeConnector::class]]);
        FakeConnector::reset();
        $this->artisan('datasets:sync')->assertSuccessful();

        FakeConnector::$custom = fn () => $this->world(null);
        $dataset = Dataset::where('key', 'fake.test')->firstOrFail();
        $run = app(IngestRunner::class)->run($dataset, 'test');
        $this->assertSame('promoted', app(Promoter::class)->promote($run)->status);
    }

    /**
     * 25 دولة بقيم متدرجة ليكون للترتيب معنى (الحد الأدنى للترتيب 20 دولة)، ثم بند غير دولي.
     *
     * @return iterable<ParsedEntity|ParsedRecord>
     */
    private function world(?float $c01In2025): iterable
    {
        foreach (range(1, 25) as $i) {
            $code = 'C'.str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            yield new ParsedEntity($code, "Country {$i}", 'country', "دولة {$i}");
            yield new ParsedRecord('fake.GDP', $code, '2023', $i * 1.0);
            yield new ParsedRecord('fake.GDP', $code, '2024', $i * 1.0 + 0.5);
            yield new ParsedRecord('fake.GDP', $code, '2025', $i === 1 && $c01In2025 !== null ? $c01In2025 : $i * 1.0 + 1.0);
        }
        yield new ParsedEntity('ZZZ', 'Unspecified', 'other', 'غير محدد');
        yield new ParsedRecord('fake.GDP', 'ZZZ', '2024', 45.0);
    }

    public function test_observations_returns_several_entities_for_one_indicator_with_source_and_freshness(): void
    {
        $this->getJson('/api/v1/observations?indicator=fake.GDP&entities=c01,C02,NOPE&from=2024')
            ->assertOk()
            ->assertJsonPath('indicator.key', 'fake.GDP')
            ->assertJsonPath('dataset.freshness.source_version', 'Fake Outlook v1')
            ->assertJsonCount(2, 'series')
            ->assertJsonPath('series.0.entity.code', 'C01')
            ->assertJsonPath('series.0.entity.name_ar', 'دولة 1')
            ->assertJsonPath('series.0.points.0', ['2024', 1.5])
            ->assertJsonPath('series.0.points.1.0', '2025');
    }

    public function test_observations_validates_its_input(): void
    {
        $this->getJson('/api/v1/observations?entities=OMN')->assertStatus(422);
        $this->getJson('/api/v1/observations?indicator=fake.GDP')->assertStatus(422);
        $this->getJson('/api/v1/observations?indicator=fake.GDP&entities=OMN&from=abc')->assertStatus(422);
        $this->getJson('/api/v1/observations?indicator=nope.X&entities=OMN')->assertNotFound();
        $tooMany = implode(',', array_map(fn ($i) => "E{$i}", range(1, 61)));
        $this->getJson("/api/v1/observations?indicator=fake.GDP&entities={$tooMany}")->assertStatus(422);
    }

    public function test_entities_lists_only_those_with_data_and_can_filter_by_type_and_indicator(): void
    {
        $all = $this->getJson('/api/v1/entities')->assertOk()->json('data');
        $this->assertCount(26, $all);

        $countries = $this->getJson('/api/v1/entities?type=country&with=fake.GDP')->json('data');
        $this->assertCount(25, $countries);
        $this->assertSame('دولة 1', array_values(array_filter($countries, fn ($c) => $c['code'] === 'C01'))[0]['name_ar']);

        $this->getJson('/api/v1/entities?type=bogus')->assertStatus(422);
    }

    public function test_latest_gives_value_previous_period_and_rank_among_countries(): void
    {
        $r = $this->getJson('/api/v1/entities/C10/latest?indicators=fake.GDP')->assertOk()->json();

        $item = $r['items'][0];
        $this->assertSame('2025', $item['period']);
        $this->assertEquals(11.0, $item['value']);
        $this->assertSame(['period' => '2024', 'value' => 10.5], $item['previous']);
        $this->assertSame(['position' => 16, 'total' => 25], $item['rank'], '25 دولة؛ 15 منها أعلى قيمة');
        $this->assertSame('fake.test', $item['dataset']['key']);
    }

    public function test_latest_respects_max_period_to_skip_forecasts(): void
    {
        $item = $this->getJson('/api/v1/entities/C10/latest?indicators=fake.GDP&max_period=2024')->json('items.0');

        $this->assertSame('2024', $item['period']);
        $this->assertEquals(10.5, $item['value']);
    }

    public function test_latest_for_unknown_entity_is_404_and_empty_indicators_are_invalid(): void
    {
        $this->getJson('/api/v1/entities/NOPE/latest?indicators=fake.GDP')->assertNotFound();
        $this->getJson('/api/v1/entities/C10/latest')->assertStatus(422);
        $this->getJson('/api/v1/entities/C10/latest?indicators=fake.GDP&max_period=bad')->assertStatus(422);
    }

    public function test_ranking_orders_by_value_with_shares_that_sum_to_the_total(): void
    {
        $r = $this->getJson('/api/v1/rankings?indicator=fake.GDP&period=2024&limit=3&type=any')->assertOk()->json();

        $this->assertSame(['ZZZ', 'C25', 'C24'], array_map(fn ($i) => $i['entity']['code'], $r['items']));
        $this->assertSame(26, $r['count']);
        $this->assertEqualsWithDelta(45.0 + array_sum(array_map(fn ($i) => $i + 0.5, range(1, 25))), $r['sum'], 0.001);
        $this->assertEqualsWithDelta($r['items'][0]['value'] / $r['sum'], $r['items'][0]['share'], 1e-9);
    }

    public function test_ranking_of_countries_only_excludes_other_entities_and_reports_the_highlight(): void
    {
        $r = $this->getJson('/api/v1/rankings?indicator=fake.GDP&period=2024&limit=2&highlight=c20')->assertOk()->json();

        $this->assertSame(25, $r['count']);
        $this->assertSame(['C25', 'C24'], array_map(fn ($i) => $i['entity']['code'], $r['items']));
        $this->assertSame(['code' => 'C20', 'value' => 20.5, 'rank' => 6, 'of' => 25], $r['highlight']);
    }

    public function test_ranking_validates_period_format(): void
    {
        $this->getJson('/api/v1/rankings?indicator=fake.GDP&period=latest')->assertStatus(422);
        $this->getJson('/api/v1/rankings?indicator=fake.GDP&period=2024&limit=999')->assertStatus(422);
    }

    public function test_overview_summarises_the_platform_and_its_last_runs(): void
    {
        $r = $this->getJson('/api/v1/overview')->assertOk()->json();

        $this->assertSame(1, $r['counts']['datasets']);
        $this->assertSame(1, $r['counts']['indicators']);
        $this->assertSame(26, $r['counts']['entities']);
        $this->assertSame(['fresh' => 1], $r['by_status']);
        $this->assertSame('fake.test', $r['recent_runs'][0]['dataset']);
        $this->assertSame('promoted', $r['recent_runs'][0]['status']);
        $this->assertNotNull($r['last_checked_at']);
    }

    public function test_responses_change_after_new_data_is_promoted_because_the_cache_is_versioned(): void
    {
        $first = $this->getJson('/api/v1/entities/C01/latest?indicators=fake.GDP')->json('items.0.value');

        $this->travel(5)->minutes();
        FakeConnector::$token = 'v2';
        FakeConnector::$custom = fn () => $this->world(2.5);
        $dataset = Dataset::where('key', 'fake.test')->firstOrFail();
        $run = app(IngestRunner::class)->run($dataset, 'test', true);
        app(Promoter::class)->promote($run);

        $second = $this->getJson('/api/v1/entities/C01/latest?indicators=fake.GDP')->json('items.0.value');
        $this->assertEquals(2.0, $first);
        $this->assertEquals(2.5, $second, 'لا قيمة قديمة من الكاش بعد الترحيل');
    }

    public function test_unknown_api_paths_return_json_404_never_the_app_shell(): void
    {
        $this->getJson('/api/v1/does-not-exist')->assertNotFound()->assertHeader('content-type', 'application/json');
    }

    public function test_disabled_datasets_are_hidden_from_the_overview_and_the_dataset_list(): void
    {
        DB::table('ops.datasets')->insert([
            'key' => 'old.gone', 'source_id' => DB::table('core.sources')->value('id'), 'name_ar' => 'قديمة', 'name_en' => 'Old', 'section' => 'x',
            'connector' => 'X', 'interval_minutes' => 60, 'enabled' => false, 'status' => 'disabled',
        ]);

        $this->assertSame(1, $this->getJson('/api/v1/overview')->json('counts.datasets'));
        $this->assertSame(['fake.test'], array_column($this->getJson('/api/v1/datasets')->json('data'), 'key'));
    }
}
