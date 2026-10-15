<?php

namespace Tests\Feature;

use App\Core\Ingestion\IngestRunner;
use App\Core\Ingestion\Promoter;
use App\Models\Dataset;
use Illuminate\Support\Facades\Http;
use Tests\DatabaseTestCase;
use Tests\Support\FakeConnector;

class ApiTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['omanilab.connectors' => [FakeConnector::class]]);
        FakeConnector::reset();
        Http::fake();
        $this->artisan('datasets:sync')->assertSuccessful();

        $run = app(IngestRunner::class)->run(Dataset::where('key', 'fake.test')->firstOrFail(), 'test');
        app(Promoter::class)->promote($run);
    }

    public function test_datasets_endpoint_exposes_freshness(): void
    {
        $this->getJson('/api/v1/datasets')
            ->assertOk()
            ->assertJsonPath('data.0.key', 'fake.test')
            ->assertJsonPath('data.0.status', 'fresh')
            ->assertJsonPath('data.0.freshness.source_version', 'Fake Outlook v1')
            ->assertJsonStructure(['data' => [['freshness' => ['last_checked_at', 'last_changed_at', 'next_check_at', 'source_published_at']]]]);
    }

    public function test_series_endpoint_returns_points_with_their_source_and_freshness(): void
    {
        $this->getJson('/api/v1/series/fake.GDP/omn')
            ->assertOk()
            ->assertJsonPath('entity.code', 'OMN')
            ->assertJsonPath('points.0', ['period' => '2023', 'value' => 1.4])
            ->assertJsonCount(3, 'points')
            ->assertJsonPath('dataset.source.key', 'fake')
            ->assertJsonPath('dataset.freshness.source_version', 'Fake Outlook v1');
    }

    public function test_series_endpoint_404s_for_unknown_indicator_or_entity(): void
    {
        $this->getJson('/api/v1/series/nope.X/OMN')->assertNotFound();
        $this->getJson('/api/v1/series/fake.GDP/ZZZ')->assertNotFound();
    }

    public function test_status_page_renders_in_arabic_with_the_dataset(): void
    {
        $this->get('/legacy/status')->assertOk()->assertSee('حالة البيانات')->assertSee('مجموعة اختبارية')->assertSee('محدّثة');
    }
}
