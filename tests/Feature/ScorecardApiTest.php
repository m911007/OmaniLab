<?php

namespace Tests\Feature;

use App\Core\Api\DataVersion;
use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\IngestRunner;
use App\Core\Ingestion\Promoter;
use App\Models\Dataset;
use Illuminate\Support\Facades\Cache;
use Tests\DatabaseTestCase;
use Tests\Support\FakeConnector;

class ScorecardApiTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['omanilab.connectors' => [FakeConnector::class]]);
        FakeConnector::reset();
        $this->artisan('datasets:sync')->assertSuccessful();

        FakeConnector::$custom = function () {
            foreach (range(1, 20) as $i) {
                $code = 'C'.str_pad((string) $i, 2, '0', STR_PAD_LEFT);
                yield new ParsedEntity($code, "Country {$i}", 'country', "دولة {$i}");
                // الجميع يتحسن بمقدار 1 سنويًا، ودولة C05 تقفز في آخر عامين
                foreach (range(2019, 2024) as $year) {
                    $bonus = $code === 'C05' && $year >= 2023 ? 40.0 : 0.0;
                    yield new ParsedRecord('fake.GDP', $code, (string) $year, $i * 1.0 + ($year - 2019) + $bonus);
                }
            }
        };
        $dataset = Dataset::where('key', 'fake.test')->firstOrFail();
        $run = app(IngestRunner::class)->run($dataset, 'test');
        $this->assertSame('promoted', app(Promoter::class)->promote($run)->status);

        config(['scorecard' => [
            'entity' => 'C05', 'peers' => ['C04', 'C06', 'C19'], 'baseline_years' => 5, 'window_years' => 3, 'max_age_years' => 6,
            'themes' => ['t' => ['label' => 'اختبار', 'indicators' => [
                ['key' => 'fake.GDP', 'polarity' => 'higher'],
                ['key' => 'nope.MISSING', 'polarity' => 'higher'],
            ]]],
        ]]);
    }

    public function test_scorecard_places_the_entity_against_the_world_and_its_peers(): void
    {
        $res = $this->getJson('/api/v1/insights/scorecard')->assertOk();

        $res->assertJsonPath('items.0.key', 'fake.GDP')
            ->assertJsonPath('items.0.period', '2024')
            ->assertJsonPath('items.0.world.rank', 1)
            ->assertJsonPath('items.0.world.of', 20)
            ->assertJsonPath('items.0.world.percentile', 100)
            ->assertJsonPath('items.0.baseline.period', '2019')
            ->assertJsonPath('items.0.trend', 'improving')
            ->assertJsonPath('items.0.class', 'strength')
            ->assertJsonPath('items.0.gcc.rank', 1)
            ->assertJsonPath('items.0.dataset.freshness.source_version', 'Fake Outlook v1')
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('themes.0.count', 1)
            ->assertJsonPath('themes.0.percentile', 100)
            ->assertJsonPath('counts.strength', 1);
        $this->assertSame(['C05', 'C19', 'C06', 'C04'], array_column($res->json('items.0.gcc.items'), 'code'));
    }

    public function test_stale_indicators_are_left_out_of_the_scorecard(): void
    {
        config(['scorecard.max_age_years' => 1]);

        $this->getJson('/api/v1/insights/scorecard')->assertOk()->assertJsonCount(0, 'items')->assertJsonPath('themes.0.count', 0);
    }

    public function test_scorecard_is_cached_per_data_version(): void
    {
        $this->getJson('/api/v1/insights/scorecard')->assertOk();

        $this->assertTrue(Cache::has('scorecard:'.DataVersion::token()));
    }
}
