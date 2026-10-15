<?php

namespace Tests\Feature;

use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRelation;
use App\Core\Ingestion\IngestRunner;
use App\Core\Ingestion\Promoter;
use App\Models\Dataset;
use App\Models\IngestRun;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\DatabaseTestCase;
use Tests\Support\FakeConnector;

class RelationsTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['omanilab.connectors' => [FakeConnector::class]]);
        FakeConnector::reset();
        $this->artisan('datasets:sync')->assertSuccessful();
    }

    /** @param  list<array{0: string, 1: string, 2: string, 3: float}>  $pairs  [مصدر، هدف، فترة، قيمة] */
    private function load(array $pairs, bool $force = false): IngestRun
    {
        FakeConnector::$payloadSalt = md5(json_encode($pairs));
        FakeConnector::$custom = function () use ($pairs) {
            foreach (['AAA', 'BBB', 'CCC', 'DDD'] as $c) {
                yield new ParsedEntity($c, "Country {$c}", 'country');
            }
            foreach ($pairs as [$s, $t, $p, $v]) {
                yield new ParsedRelation('fake.GDP', $s, $t, $p, $v);
            }
        };
        $run = app(IngestRunner::class)->run(Dataset::where('key', 'fake.test')->firstOrFail(), 'test', $force);

        return $run->status === 'staged' ? app(Promoter::class)->promote($run) : $run;
    }

    private function relationCount(): int
    {
        return DB::table('core.relations')->count();
    }

    public function test_relations_are_promoted_between_two_entities(): void
    {
        $run = $this->load([['AAA', 'BBB', '2023', 10.0], ['AAA', 'CCC', '2023', 5.0], ['BBB', 'AAA', '2023', 7.0]]);

        $this->assertSame('promoted', $run->status);
        $this->assertSame(3, $this->relationCount());
        $this->assertSame(0, DB::table('staging.relations')->count(), 'staging يُنظَّف');
        $this->assertSame(0, DB::table('core.observations')->count());
    }

    public function test_direction_matters_and_pairs_are_unique_per_period(): void
    {
        $this->load([['AAA', 'BBB', '2023', 10.0], ['BBB', 'AAA', '2023', 7.0], ['AAA', 'BBB', '2024', 12.0]]);

        $a = DB::table('core.entities')->where('code', 'AAA')->value('id');
        $b = DB::table('core.entities')->where('code', 'BBB')->value('id');
        $this->assertEquals(10.0, DB::table('core.relations')->where('source_entity_id', $a)->where('target_entity_id', $b)->where('period', '2023')->value('value'));
        $this->assertEquals(7.0, DB::table('core.relations')->where('source_entity_id', $b)->where('target_entity_id', $a)->where('period', '2023')->value('value'));
        $this->assertSame(3, $this->relationCount());
    }

    public function test_self_relations_are_dropped_and_identical_duplicates_collapsed(): void
    {
        $run = $this->load([['AAA', 'AAA', '2023', 5.0], ['AAA', 'BBB', '2023', 10.0], ['aaa', 'bbb', '2023', 10.0]]);

        $this->assertSame('promoted', $run->status);
        $this->assertSame(1, $this->relationCount());
        $this->assertSame(1, $run->meta['duplicates_collapsed']);
    }

    public function test_conflicting_duplicate_pairs_quarantine_the_run(): void
    {
        $run = $this->load([['AAA', 'BBB', '2023', 10.0], ['AAA', 'BBB', '2023', 11.0]]);

        $this->assertSame('quarantined', $run->status);
        $this->assertSame(0, $this->relationCount());
    }

    public function test_second_identical_run_is_unchanged_and_a_changed_value_updates_one_row(): void
    {
        $pairs = [['AAA', 'BBB', '2023', 10.0], ['AAA', 'CCC', '2023', 5.0]];
        $this->load($pairs);
        $this->assertSame('unchanged', $this->load($pairs)->status);

        FakeConnector::$token = 'v2';
        $run = $this->load([['AAA', 'BBB', '2023', 10.5], ['AAA', 'CCC', '2023', 5.0]]);

        $this->assertSame('promoted', $run->status);
        $this->assertSame(1, $run->promoted_count);
    }

    public function test_pairs_the_source_stopped_publishing_in_a_republished_period_are_removed(): void
    {
        $this->load([['AAA', 'BBB', '2023', 10.0], ['AAA', 'CCC', '2023', 5.0], ['AAA', 'DDD', '2023', 2.0]], true);

        FakeConnector::$token = 'v2';
        $run = $this->load([['AAA', 'BBB', '2023', 10.0], ['AAA', 'CCC', '2023', 5.0]]);

        $this->assertSame('promoted', $run->status);
        $this->assertSame(1, $run->meta['removed_points']);
        $this->assertSame(2, $this->relationCount());
    }

    public function test_pairs_with_unknown_entities_are_skipped_and_counted(): void
    {
        $run = $this->load([['AAA', 'BBB', '2023', 10.0], ['AAA', 'ZZZ9', '2023', 3.0]]);

        $this->assertSame('promoted', $run->status);
        $this->assertSame(1, $this->relationCount());
        $this->assertSame(1, $run->meta['relations_skipped']);
    }

    public function test_out_of_range_pair_value_quarantines_without_touching_the_good_data(): void
    {
        $this->load([['AAA', 'BBB', '2023', 10.0]]);
        FakeConnector::$token = 'v2';

        $run = $this->load([['AAA', 'BBB', '2023', 9999.0]]);

        $this->assertSame('quarantined', $run->status);
        $this->assertEquals(10.0, DB::table('core.relations')->value('value'));
    }

    public function test_the_relations_api_ranks_partners_in_both_directions(): void
    {
        $this->load([['AAA', 'BBB', '2023', 30.0], ['AAA', 'CCC', '2023', 10.0], ['BBB', 'AAA', '2023', 4.0], ['CCC', 'AAA', '2023', 6.0], ['AAA', 'BBB', '2022', 1.0]]);

        $out = $this->getJson('/api/v1/relations?indicator=fake.GDP&entity=aaa&period=2023')->assertOk()->json();
        $this->assertSame(['BBB', 'CCC'], array_column(array_column($out['items'], 'entity'), 'code'));
        $this->assertEquals(0.75, $out['items'][0]['share']);
        $this->assertSame(2, $out['count']);

        $in = $this->getJson('/api/v1/relations?indicator=fake.GDP&entity=AAA&period=2023&direction=in')->assertOk()->json();
        $this->assertSame(['CCC', 'BBB'], array_column(array_column($in['items'], 'entity'), 'code'));
        $this->assertSame('fake.test', $in['dataset']['key']);
    }

    public function test_the_relation_series_endpoint_and_validation(): void
    {
        $this->load([['AAA', 'BBB', '2022', 8.0], ['AAA', 'BBB', '2023', 10.0]]);

        $this->getJson('/api/v1/relations/series?indicator=fake.GDP&source=AAA&target=BBB')->assertOk()
            ->assertJsonPath('points.0.0', '2022')->assertJsonPath('points.1.1', 10);

        $this->getJson('/api/v1/relations?indicator=fake.GDP&entity=AAA')->assertStatus(422);
        $this->getJson('/api/v1/relations?indicator=fake.GDP&entity=NOPE&period=2023')->assertNotFound();
        $this->getJson('/api/v1/relations?indicator=x&entity=AAA&period=2023&direction=sideways')->assertStatus(422);
    }
}
