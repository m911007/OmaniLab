<?php

namespace Tests\Feature;

use App\Core\Ingestion\Data\ParsedAttribute;
use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\IngestRunner;
use App\Core\Ingestion\Promoter;
use App\Models\Dataset;
use Illuminate\Support\Facades\Cache;
use Tests\DatabaseTestCase;
use Tests\Support\FakeConnector;

class CountryDirectoryTest extends DatabaseTestCase
{
    public function test_directory_lists_countries_with_latest_values_region_and_skips_empty_entities(): void
    {
        Cache::flush();
        config(['omanilab.connectors' => [FakeConnector::class]]);
        FakeConnector::reset();
        $this->artisan('datasets:sync')->assertSuccessful();
        FakeConnector::$custom = fn () => [
            new ParsedEntity('OMN', 'Oman', 'country', 'عُمان'),
            new ParsedEntity('EMP', 'Empty land', 'country', null),
            new ParsedAttribute('OMN', 'region', 'Middle East & North Africa'),
            new ParsedAttribute('OMN', 'capital_city', 'Muscat'),
            new ParsedRecord('fake.value', 'OMN', '2023', 5.0),
            new ParsedRecord('fake.value', 'OMN', '2024', 7.0),
        ];
        $run = app(IngestRunner::class)->run(Dataset::where('key', 'fake.test')->firstOrFail(), 'test');
        app(Promoter::class)->promote($run);

        $res = $this->getJson('/api/v1/countries/directory')->assertOk();
        $codes = array_column($res->json('data'), 'code');

        $this->assertNotContains('EMP', $codes);
        $this->assertArrayHasKey('gdp', $res->json('columns'));
    }
}
