<?php

namespace Tests\Feature;

use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Modules\Gcc\Connectors\GccStatEconomyConnector;
use App\Modules\Gcc\Connectors\GccStatEnergyTourismConnector;
use App\Modules\Gcc\Connectors\GccStatSocietyConnector;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class GccStatConnectorTest extends TestCase
{
    /** @return array<string, mixed> */
    private function sdmx(string $seriesDim = 'GCC_COUNTRY'): array
    {
        return ['data' => [
            'dataSets' => [['series' => [
                '0' => ['observations' => ['0' => ['1.5'], '1' => ['2.5']]],
                '1' => ['observations' => ['0' => ['9'], '1' => [null]]],
            ]]],
            'structure' => ['dimensions' => [
                'series' => [['id' => $seriesDim, 'values' => [['id' => 'OMN'], ['id' => 'QAT']]]],
                'observation' => [['id' => 'TIME_PERIOD', 'values' => [['id' => '2010'], ['id' => '2011']]]],
            ]],
        ]];
    }

    public function test_parses_series_by_index_skipping_empty_observations_and_declares_gcc_entities(): void
    {
        Http::fake(['sdmx.marsa.gccstat.org/*' => Http::response($this->sdmx())]);
        $c = new GccStatEconomyConnector;

        $items = iterator_to_array($c->parse($c->fetch()), false);
        $records = array_values(array_filter($items, fn ($i) => $i instanceof ParsedRecord && $i->indicatorKey === 'gccstat.GDP_CUR'));

        $this->assertCount(3, $records, 'null يُتخطى');
        $this->assertEquals(['OMN|2010|1.5', 'OMN|2011|2.5', 'QAT|2010|9'], array_map(fn ($r) => "{$r->entityCode}|{$r->period}|{$r->value}", $records));

        $entities = collect($items)->filter(fn ($i) => $i instanceof ParsedEntity)->keyBy('code');
        $this->assertSame('سلطنة عُمان', $entities['OMN']->nameAr);
        $this->assertSame('group', $entities['GCC']->type);
        $this->assertCount(7, $entities);
    }

    public function test_every_flow_is_requested_with_the_sdmx_accept_header(): void
    {
        Http::fake(['sdmx.marsa.gccstat.org/*' => Http::response($this->sdmx())]);

        (new GccStatSocietyConnector)->fetch();

        Http::assertSent(fn (Request $r) => $r->header('Accept')[0] === 'application/vnd.sdmx.data+json' && str_contains($r->url(), 'GCCSTAT.MOBILE,MOBILE_DF_POP,1.0'));
        Http::assertSentCount(count((new GccStatSocietyConnector)->definition()->indicators));
    }

    public function test_payload_hash_is_stable_when_only_the_response_envelope_changes(): void
    {
        $withMeta = fn (string $prepared) => ['meta' => ['id' => 'IREF'.$prepared, 'prepared' => $prepared]] + $this->sdmx();

        Http::fake(['sdmx.marsa.gccstat.org/*' => Http::response($withMeta('2026-10-14T22:52:33'))]);
        $first = (new GccStatEconomyConnector)->fetch()->sha256;

        $this->freshHttp();
        Http::fake(['sdmx.marsa.gccstat.org/*' => Http::response($withMeta('2026-10-15T03:25:03'))]);
        $second = (new GccStatEconomyConnector)->fetch()->sha256;

        $this->assertSame($first, $second);
    }

    public function test_a_changed_dimension_layout_stops_the_run_instead_of_misreading_data(): void
    {
        Http::fake(['sdmx.marsa.gccstat.org/*' => Http::response($this->sdmx('REF_AREA'))]);
        $c = new GccStatEnergyTourismConnector;
        $payload = $c->fetch();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('تغيّر شكل أبعاد التدفق');
        iterator_to_array($c->parse($payload));
    }

    public function test_no_cheap_probe_so_change_detection_relies_on_payload_hash(): void
    {
        $this->assertNull((new GccStatEconomyConnector)->probe()->token);
    }

    public function test_definitions_have_unique_keys_rules_and_correct_frequencies(): void
    {
        $keys = [];
        foreach ([new GccStatEconomyConnector, new GccStatSocietyConnector, new GccStatEnergyTourismConnector] as $c) {
            $def = $c->definition();
            foreach ($def->indicators as $i) {
                $keys[] = $i->key;
                $this->assertNotEmpty($i->rules);
            }
        }
        $this->assertSame($keys, array_values(array_unique($keys)), 'مفاتيح المؤشرات فريدة عبر المجموعات الثلاث');

        $freq = collect((new GccStatEconomyConnector)->definition()->indicators)->pluck('frequency', 'key');
        $this->assertSame('M', $freq['gccstat.M2']);
        $this->assertSame('Q', $freq['gccstat.GDP_CUR_Q']);
        $this->assertSame('A', $freq['gccstat.GDP_CUR']);
    }
}
