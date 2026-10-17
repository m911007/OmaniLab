<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Modules\Places\Connectors\OsmPlacesConnector;
use App\Modules\Places\GovernorateLocator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use App\Core\Ingestion\IngestRunner;
use App\Core\Ingestion\Promoter;
use Tests\DatabaseTestCase;

class PlacesTest extends DatabaseTestCase
{
    public function test_locator_finds_the_governorate_of_known_points(): void
    {
        $locator = new GovernorateLocator;

        $this->assertSame('مسقط', $locator->find(23.59, 58.41));
        $this->assertSame('ظفار', $locator->find(17.02, 54.09));
        $this->assertNull($locator->find(30.0, 40.0));
    }

    public function test_osm_places_are_ingested_as_place_entities_and_served_by_the_api(): void
    {
        Cache::flush();
        config(['omanilab.connectors' => [OsmPlacesConnector::class], 'places.categories' => [
            'university' => ['group' => 'education', 'ar' => 'الجامعات', 'en' => 'Universities', 'filter' => ['["amenity"="university"]']],
            'dam' => ['group' => 'resources', 'ar' => 'السدود', 'en' => 'Dams', 'filter' => ['["waterway"="dam"]'], 'allow_unnamed' => true],
        ]]);
        $this->freshHttp();
        Http::fake(function ($request) {
            $isDam = str_contains((string) $request['data'], 'waterway');

            return Http::response(['elements' => $isDam
                ? [['type' => 'way', 'id' => 9, 'center' => ['lat' => 23.2, 'lon' => 57.9], 'tags' => []]]
                : [
                    ['type' => 'node', 'id' => 1, 'lat' => 23.59, 'lon' => 58.41, 'tags' => ['name' => 'جامعة السلطان قابوس', 'name:en' => 'Sultan Qaboos University', 'website' => 'squ.edu.om']],
                    ['type' => 'node', 'id' => 2, 'lat' => 23.6, 'lon' => 58.4, 'tags' => []],
                ]]);
        });
        $this->artisan('datasets:sync')->assertSuccessful();

        $run = app(IngestRunner::class)->run(Dataset::where('key', 'osm.places')->firstOrFail(), 'test');
        $this->assertSame('promoted', app(Promoter::class)->promote($run)->status);

        $stats = $this->getJson('/api/v1/places/stats')->assertOk()->json('categories');
        $this->assertSame([1, 1], [$stats[0]['count'], $stats[1]['count']]);
        $this->getJson('/api/v1/places?category=university')->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('data.0.name_en', 'Sultan Qaboos University')->assertJsonPath('data.0.governorate', 'مسقط')->assertJsonPath('data.0.website', 'https://squ.edu.om');
        $this->getJson('/api/v1/places?group=resources')->assertJsonPath('total', 1);
        $this->getJson('/api/v1/places?q=قابوس')->assertJsonPath('total', 1);
    }
}
