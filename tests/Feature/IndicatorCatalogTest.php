<?php

namespace Tests\Feature;

use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\IngestRunner;
use App\Core\Ingestion\Promoter;
use App\Http\Controllers\Api\V1\IndicatorController;
use App\Models\Dataset;
use Illuminate\Support\Facades\Cache;
use Tests\DatabaseTestCase;
use Tests\Support\FakeConnector;

class IndicatorCatalogTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['omanilab.connectors' => [FakeConnector::class]]);
        FakeConnector::reset();
        $this->artisan('datasets:sync')->assertSuccessful();

        FakeConnector::$custom = function () {
            yield new ParsedEntity('OMN', 'Oman', 'country', 'عُمان');
            yield new ParsedEntity('SAU', 'Saudi Arabia', 'country', 'السعودية');
            foreach (range(2020, 2024) as $year) {
                yield new ParsedRecord('fake.GDP', 'OMN', (string) $year, 2.0);
                yield new ParsedRecord('fake.GDP', 'SAU', (string) $year, 3.0);
            }
        };
        $run = app(IngestRunner::class)->run(Dataset::where('key', 'fake.test')->firstOrFail(), 'test');
        $this->assertSame('promoted', app(Promoter::class)->promote($run)->status);
    }

    public function test_arabic_letters_are_normalised_for_search(): void
    {
        $this->assertSame('اسعار السلع والطاقه', IndicatorController::normalise('أسعار السِّلع والطاقة'));
        $this->assertSame('علي', IndicatorController::normalise('على'));
    }

    public function test_the_catalog_lists_indicators_with_their_coverage(): void
    {
        $this->getJson('/api/v1/indicators?omn=1')->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.key', 'fake.GDP')
            ->assertJsonPath('data.0.last_period', '2024')
            ->assertJsonPath('data.0.entities', 2)
            ->assertJsonPath('data.0.omn', true);
    }

    public function test_an_indicator_page_defaults_to_oman_and_lists_entities(): void
    {
        $this->getJson('/api/v1/indicators/fake.GDP')->assertOk()
            ->assertJsonPath('data.default_entity', 'OMN')
            ->assertJsonPath('data.countries', 2)
            ->assertJsonPath('data.dataset.key', 'fake.test');
        $this->getJson('/api/v1/indicators/nope.MISSING')->assertNotFound();
    }

    public function test_search_finds_indicators_and_countries_in_arabic(): void
    {
        $this->getJson('/api/v1/search?q='.urlencode('نمو'))->assertOk()->assertJsonPath('data.indicators.0.key', 'fake.GDP');
        $this->getJson('/api/v1/search?q='.urlencode('السعوديه'))->assertOk()->assertJsonPath('data.entities.0.code', 'SAU');
        $this->getJson('/api/v1/search?q=a')->assertStatus(422);
    }
}
