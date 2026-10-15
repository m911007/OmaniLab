<?php

namespace Tests\Feature;

use App\Core\Ingestion\Data\ParsedRecord;
use App\Modules\Ncsi\Connectors\NcsiNationalAccountsConnector;
use App\Modules\Ncsi\Connectors\NcsiPricesConnector;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class NcsiConnectorTest extends TestCase
{
    private const BASE = 'https://data.gov.om/api/1.0';

    protected function setUp(): void
    {
        parent::setUp();
        config(['omanilab.ingest.knoema_throttle_microseconds' => 0]);
    }

    /** @param  list<array<string, mixed>>  $pivotRows */
    private function fakeCpi(array $pivotRows): void
    {
        $this->freshHttp();
        Http::fake([
            self::BASE.'/meta/dataset/rauaefb/dimension/regions*' => Http::response(['items' => [['key' => 1000000, 'hasData' => true], ['key' => 1000010, 'hasData' => true]]]),
            self::BASE.'/meta/dataset/rauaefb/dimension/indicators*' => Http::response(['items' => [['key' => 1000050, 'hasData' => true]]]),
            self::BASE.'/meta/dataset/rauaefb/dimension/commodities*' => Http::response(['items' => [['key' => 1000000, 'hasData' => false], ['key' => 1000020, 'hasData' => true]]]),
            self::BASE.'/meta/dataset/rauaefb?*' => Http::response([
                'lastDataChangedOn' => '2026-10-14T09:11:26.877',
                'dimensions' => [['id' => 'regions'], ['id' => 'indicators'], ['id' => 'commodities']],
            ]),
            self::BASE.'/data/pivot*' => Http::response(['data' => $pivotRows]),
        ]);
    }

    public function test_probe_uses_the_datasets_last_data_change(): void
    {
        $this->fakeCpi([]);

        $p = (new NcsiPricesConnector)->probe();

        $this->assertSame('2026-10-14T09:11:26.877', $p->token);
        $this->assertSame('2026-10-14', $p->publishedAt->toDateString());
        $this->assertStringContainsString('rauaefb', $p->version);
    }

    public function test_probe_fails_loudly_when_the_source_stops_reporting_its_change_date(): void
    {
        Http::fake([self::BASE.'/*' => Http::response(['dimensions' => []])]);

        $this->expectException(RuntimeException::class);
        (new NcsiPricesConnector)->probe();
    }

    public function test_pivot_request_pins_every_side_dimension_to_one_member(): void
    {
        $this->fakeCpi([['Time' => '2026-09-01T00:00:00Z', 'Value' => 110.7, 'Frequency' => 'M']]);

        (new NcsiPricesConnector)->fetch();

        Http::assertSent(function (Request $r) {
            if (! str_contains($r->url(), '/data/pivot')) {
                return false;
            }
            /** @var array{Dataset: string, Stub: list<array{DimensionId: string}>, Filter: list<array{DimensionId: string, Members: list<int>}>, Frequencies: list<string>} $b */
            $b = $r->data();
            $filters = [];
            foreach ($b['Filter'] as $f) {
                $filters[$f['DimensionId']] = $f['Members'];
            }

            return $b['Dataset'] === 'rauaefb' && $b['Stub'][0]['DimensionId'] === 'indicators'
                && $filters === ['regions' => [1000000], 'commodities' => [1000020]]  // أول عضو فيه بيانات
                && $b['Frequencies'] === ['M'];
        });
    }

    public function test_only_rows_of_the_requested_frequency_are_kept_and_periods_are_normalised(): void
    {
        $this->fakeCpi([
            ['Time' => '2026-08-01T00:00:00Z', 'Value' => 110.2, 'Frequency' => 'M'],
            ['Time' => '2026-09-01T00:00:00Z', 'Value' => 110.7, 'Frequency' => 'M'],
            ['Time' => '2026-01-01T00:00:00Z', 'Value' => 999.0, 'Frequency' => 'A'],   // تردد آخر مختلط في الرد
            ['Time' => '2026-07-01T00:00:00Z', 'Value' => null, 'Frequency' => 'M'],
        ]);
        $c = new NcsiPricesConnector;

        $records = collect(iterator_to_array($c->parse($c->fetch()), false))
            ->filter(fn ($i) => $i instanceof ParsedRecord && $i->indicatorKey === 'ncsi.CPI_INDEX')
            ->map(fn ($r) => "{$r->entityCode}|{$r->period}|{$r->value}")->all();

        $this->assertSame(['OMN|2026-08|110.2', 'OMN|2026-09|110.7'], array_values($records));
    }

    public function test_an_empty_series_aborts_because_dimensions_or_members_probably_changed(): void
    {
        $this->fakeCpi([]);

        $this->expectExceptionMessage('CPI_INDEX');
        (new NcsiPricesConnector)->fetch();
    }

    public function test_annual_periods_use_only_the_year(): void
    {
        $this->freshHttp();
        Http::fake([
            self::BASE.'/meta/dataset/zoangf/dimension/*' => Http::response(['items' => [['key' => 1000000, 'hasData' => true]]]),
            self::BASE.'/meta/dataset/zoangf?*' => Http::response(['lastDataChangedOn' => '2026-08-31T05:49:48', 'dimensions' => [['id' => 'regions'], ['id' => 'indicators'], ['id' => 'price-type']]]),
            self::BASE.'/data/pivot*' => Http::response(['data' => [['Time' => '2024-01-01T00:00:00Z', 'Value' => 41194.3, 'Frequency' => 'A']]]),
        ]);
        $c = new NcsiNationalAccountsConnector;

        $records = collect(iterator_to_array($c->parse($c->fetch()), false))->filter(fn ($i) => $i instanceof ParsedRecord && $i->indicatorKey === 'ncsi.GDP_CURRENT');

        $this->assertSame('2024', $records->first()->period);
    }
}
