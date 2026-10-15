<?php

namespace Tests\Feature;

use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\ParsedRelation;
use App\Modules\Wits\Connectors\WitsTradeConnector;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\DatabaseTestCase;

class WitsConnectorTest extends DatabaseTestCase
{
    /**
     * @param  array<string, array<string, float>>  $data  [مُبلِّغ => [شريك => قيمة]]
     * @return array<string, mixed>
     */
    private function sdmx(array $data): array
    {
        $reporters = array_keys($data);
        $partners = array_values(array_unique(array_merge(...array_values(array_map('array_keys', $data)))));
        $series = [];
        foreach ($data as $r => $byPartner) {
            foreach ($byPartner as $p => $v) {
                $series['0:'.array_search($r, $reporters, true).':'.array_search($p, $partners, true).':0:0'] = ['attributes' => [], 'observations' => ['0' => [$v, 0]]];
            }
        }

        return [
            'dataSets' => [['series' => $series]],
            'structure' => ['dimensions' => ['series' => [
                ['id' => 'FREQ', 'values' => [['id' => 'A']]],
                ['id' => 'REPORTER', 'values' => array_map(fn ($c) => ['id' => $c], $reporters)],
                ['id' => 'PARTNER', 'values' => array_map(fn ($c) => ['id' => $c], $partners)],
                ['id' => 'PRODUCTCODE', 'values' => [['id' => 'Total']]],
                ['id' => 'INDICATOR', 'values' => [['id' => 'XPRT-TRD-VL']]],
            ]]],
        ];
    }

    private function seedCountries(int $n = 160): void
    {
        foreach (range(1, $n) as $i) {
            DB::table('core.entities')->insert(['code' => sprintf('K%03d', $i), 'type' => 'country', 'name_en' => "K{$i}"]);
        }
        foreach (['OMN', 'CHN', 'ARE'] as $c) {
            DB::table('core.entities')->insert(['code' => $c, 'type' => 'country', 'name_en' => $c]);
        }
    }

    /** @return array<string, array<string, float>> */
    private function reporterTotals(int $count): array
    {
        $out = [];
        foreach (range(1, $count) as $i) {
            $out[sprintf('K%03d', $i)] = ['WLD' => 1000.0 + $i];
        }

        return $out;
    }

    public function test_probe_walks_back_to_the_latest_year_with_enough_reporters(): void
    {
        $this->freshHttp();
        $thisYear = (int) date('Y');
        Http::fake(function (Request $r) use ($thisYear) {
            if (preg_match('#/year/(\d{4})/#', $r->url(), $m) && (int) $m[1] === $thisYear - 2) {
                return Http::response($this->sdmx($this->reporterTotals(120)));
            }

            return Http::response('NoRecordsFound', 404);
        });

        $p = (new WitsTradeConnector)->probe();

        $this->assertSame(($thisYear - 2).':120', $p->token);
        $this->assertStringContainsString((string) ($thisYear - 2), $p->version);
    }

    public function test_probe_fails_when_no_recent_year_has_enough_reporters(): void
    {
        $this->freshHttp();
        Http::fake(fn () => Http::response($this->sdmx($this->reporterTotals(20))));

        $this->expectException(RuntimeException::class);
        (new WitsTradeConnector)->probe();
    }

    public function test_fetch_and_parse_produce_totals_and_bilateral_relations_for_known_countries_only(): void
    {
        $this->freshHttp();
        $this->seedCountries();
        $year = (int) date('Y') - 1;

        Http::fake(function (Request $r) use ($year) {
            $url = $r->url();
            if (! preg_match('#/reporter/([A-Z0-9]+)/year/(\d{4})/partner/([A-Z]+)/product/Total/indicator/([A-Z-]+)#', $url, $m)) {
                return Http::response('bad', 500);
            }
            [, $reporter, $y, $partner, $indicator] = $m;
            if ((int) $y !== $year) {
                return Http::response('NoRecordsFound', 404); // السنة السابقة بلا بيانات: نافذة السنتين تتحمل ذلك
            }
            if ($reporter === 'ALL') {
                return Http::response($this->sdmx(array_merge($this->reporterTotals(110), ['OMN' => ['WLD' => 50_000.0], 'CHN' => ['WLD' => 900_000.0]])));
            }
            if ($partner === 'ALL' && $reporter === 'OMN') {
                $flow = $indicator === 'XPRT-TRD-VL' ? 40_000.0 : 25_000.0;

                return Http::response($this->sdmx(['OMN' => ['CHN' => $flow, 'ARE' => $flow / 4, 'WLD' => 50_000.0, 'EUN' => 999.0, 'ZZZ' => 5.0, 'OMN' => 1.0, 'K001' => 0.0]]));
            }

            return Http::response('NoRecordsFound', 404);
        });
        $c = new WitsTradeConnector;

        $items = iterator_to_array($c->parse($c->fetch()), false);
        $relations = collect($items)->filter(fn (mixed $i) => $i instanceof ParsedRelation)->map(fn ($r) => "{$r->indicatorKey}|{$r->sourceCode}>{$r->targetCode}|{$r->period}|{$r->value}")->sort()->values()->all();
        $totals = collect($items)->filter(fn ($i) => $i instanceof ParsedRecord && $i->entityCode === 'OMN')->map(fn ($r) => "{$r->indicatorKey}|{$r->period}|{$r->value}")->sort()->values()->all();

        $this->assertSame([
            "wits.EXPORTS_TO|OMN>ARE|{$year}|10000",
            "wits.EXPORTS_TO|OMN>CHN|{$year}|40000",
            "wits.IMPORTS_FROM|OMN>ARE|{$year}|6250",
            "wits.IMPORTS_FROM|OMN>CHN|{$year}|25000",
        ], $relations, 'مجموعات (WLD/EUN) وأكواد غير معروفة وذات الدولة وقيمة الصفر تُتخطى');
        $this->assertSame(["wits.EXPORTS_TOTAL|{$year}|50000", "wits.IMPORTS_TOTAL|{$year}|50000"], $totals);
    }

    public function test_parse_waits_for_the_country_table(): void
    {
        $this->freshHttp();
        Http::fake(fn () => Http::response($this->sdmx($this->reporterTotals(120))));
        $c = new WitsTradeConnector;
        $payload = $c->fetch();

        $this->expectExceptionMessage('جدول الدول غير جاهز');
        iterator_to_array($c->parse($payload));
    }

    public function test_a_changed_dimension_layout_aborts_instead_of_misreading(): void
    {
        $this->freshHttp();
        $bad = $this->sdmx($this->reporterTotals(120));
        $bad['structure']['dimensions']['series'][1]['id'] = 'REF_AREA';
        Http::fake(fn () => Http::response($bad));

        $this->expectExceptionMessage('تغيّر شكل أبعاد السلسلة');
        (new WitsTradeConnector)->fetch();
    }

    public function test_definition_declares_totals_and_two_bilateral_indicators(): void
    {
        $keys = array_map(fn ($i) => $i->key, (new WitsTradeConnector)->definition()->indicators);

        $this->assertSame(['wits.EXPORTS_TOTAL', 'wits.IMPORTS_TOTAL', 'wits.EXPORTS_TO', 'wits.IMPORTS_FROM'], $keys);
    }
}
