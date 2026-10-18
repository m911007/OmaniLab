<?php

namespace Tests\Feature;

use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\ParsedRelation;
use App\Modules\Comtrade\Connectors\ComtradeProductsConnector;
use App\Modules\Fao\Connectors\FaoFoodSecurityConnector;
use App\Modules\Ilo\Connectors\IloLabourConnector;
use App\Modules\Macro\Connectors\ImfCpiConnector;
use App\Modules\Macro\Connectors\ImfMonthlyTradeConnector;
use App\Modules\Macro\Connectors\ImfFossilSubsidiesConnector;
use App\Modules\Macro\Connectors\ImfIipConnector;
use App\Modules\Macro\Connectors\ImfNdGainConnector;
use App\Modules\Markets\Connectors\EiaSpotPricesConnector;
use App\Modules\Markets\Connectors\ImfCommodityPricesConnector;
use App\Modules\Markets\Connectors\UsTreasuryYieldsConnector;
use App\Modules\Markets\Connectors\WorldBankPinkSheetConnector;
use App\Modules\Oec\Connectors\OecComplexityConnector;
use App\Modules\Oec\Connectors\OecProductsConnector;
use App\Modules\Owid\Connectors\OwidEnergyConnector;
use App\Modules\Owid\Connectors\OwidIndicesConnector;
use App\Modules\Unesco\Connectors\UnescoUisConnector;
use App\Modules\Who\Connectors\WhoHealthConnector;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class GlobalOrgConnectorsTest extends TestCase
{
    /**
     * @param  iterable<mixed>  $parsed
     * @return list<string>
     */
    private function records(iterable $parsed): array
    {
        return collect(iterator_to_array($parsed, false))->filter(fn (mixed $i) => $i instanceof ParsedRecord)
            ->map(fn (ParsedRecord $r) => "{$r->indicatorKey}|{$r->entityCode}|{$r->period}|{$r->value}")->values()->all();
    }

    public function test_imf_commodities_parse_monthly_periods_and_skip_metadata_and_unknown_series(): void
    {
        $this->freshHttp();
        $header = 'DATAFLOW,COUNTRY,INDICATOR,DATA_TRANSFORMATION,FREQUENCY,TIME_PERIOD,OBS_VALUE,UPDATE_DATE';
        Http::fake(fn () => Http::response(implode("\n", [
            $header,
            'IMF.RES:PCPS(9.0.0),,,,,,,"2026-10-06T17:27:04Z"',
            'IMF.RES:PCPS(9.0.0),G001,POILBRE,USD,M,2026-M09,102.01,2026-10-06T17:27:04Z',
            'IMF.RES:PCPS(9.0.0),G001,POILBRE,USD,M,2026-M08,,2026-10-06T17:27:04Z',
            'IMF.RES:PCPS(9.0.0),G001,PUNKNOWN,USD,M,2026-M09,5,2026-10-06T17:27:04Z',
            'IMF.RES:PCPS(9.0.0),G001,PGOLD,USD,A,2026,5,2026-10-06T17:27:04Z',
        ])."\n"));
        $c = new ImfCommodityPricesConnector;

        $records = array_unique($this->records($c->parse($c->fetch())));

        $this->assertSame(['imf.pcps.poilbre|WLD|2026-09|102.01'], array_values($records));
    }

    public function test_imf_commodities_probe_reports_the_publication_date(): void
    {
        $this->freshHttp();
        Http::fake(fn () => Http::response("DATAFLOW,COUNTRY,INDICATOR,DATA_TRANSFORMATION,FREQUENCY,TIME_PERIOD,OBS_VALUE,UPDATE_DATE\nIMF.RES:PCPS(9.0.0),G001,POILBRE,USD,M,2026-M09,102.01,2026-10-06T17:27:04Z\n"));

        $p = (new ImfCommodityPricesConnector)->probe();

        $this->assertSame('PCPS 2026-M09', $p->version);
        $this->assertSame('2026-10-06', $p->publishedAt->toDateString());
    }

    public function test_imf_commodities_fail_loudly_on_a_changed_response(): void
    {
        $this->freshHttp();
        Http::fake(fn () => Http::response('<html>moved</html>'));

        $this->expectException(RuntimeException::class);
        (new ImfCommodityPricesConnector)->fetch();
    }

    public function test_treasury_yields_convert_us_dates_and_skip_blank_tenors(): void
    {
        $this->freshHttp();
        Http::fake(fn () => Http::response("Date,\"3 Mo\",\"2 Yr\",\"10 Yr\",\"30 Yr\"\n10/14/2026,4.28,4.82,5.19,\n"));
        $c = new UsTreasuryYieldsConnector;

        $records = array_unique($this->records($c->parse($c->fetch())));

        $this->assertEqualsCanonicalizing(['us.yield_3m|USA|2026-10-14|4.28', 'us.yield_2y|USA|2026-10-14|4.82', 'us.yield_10y|USA|2026-10-14|5.19'], array_values($records));
    }

    public function test_open_meteo_publishes_complete_months_only_and_counts_hot_days(): void
    {
        $this->freshHttp();
        $times = [];
        $mean = [];
        $max = [];
        $rain = [];
        foreach (range(1, 30) as $d) {
            $times[] = sprintf('2026-06-%02d', $d);
            $mean[] = 30.0;
            $max[] = $d <= 10 ? 41.0 : 35.0;
            $rain[] = 0.5;
        }
        foreach (range(1, 5) as $d) {
            $times[] = sprintf('2026-07-%02d', $d);
            $mean[] = 33.0;
            $max[] = 38.0;
            $rain[] = 0.0;
        }
        Http::fake(fn () => Http::response(['daily' => ['time' => $times, 'temperature_2m_mean' => $mean, 'temperature_2m_max' => $max, 'precipitation_sum' => $rain]]));
        $c = new \App\Modules\Climate\Connectors\OpenMeteoClimateConnector;

        $records = array_filter($this->records($c->parse($c->fetch())), fn (string $r) => str_contains($r, '_muscat|'));

        $this->assertEqualsCanonicalizing([
            'climate.temp_mean_muscat|OMN|2026-06|30', 'climate.temp_max_muscat|OMN|2026-06|37', 'climate.hot_days_muscat|OMN|2026-06|10', 'climate.precip_muscat|OMN|2026-06|15',
        ], array_values($records));
    }

    public function test_imf_monthly_trade_keeps_world_totals_derives_balance_and_links_omani_partners(): void
    {
        $this->freshHttp();
        Http::fake(fn () => Http::response(implode("\n", [
            'DATAFLOW,COUNTRY,INDICATOR,COUNTERPART_COUNTRY,FREQUENCY,TIME_PERIOD,OBS_VALUE',
            'x,OMN,XG_FOB_USD,G001,M,2026-M06,3000',
            'x,OMN,MG_CIF_USD,G001,M,2026-M06,5000',
            'x,OMN,XG_FOB_USD,CHN,M,2026-M06,120',
            'x,SAU,XG_FOB_USD,CHN,M,2026-M06,900',
            'x,OMN,XG_FOB_USD,G001,M,2026-M07,',
        ])."\n"));
        $c = new ImfMonthlyTradeConnector;

        $items = iterator_to_array($c->parse($c->fetch()), false);
        $records = array_map(fn ($i) => $i instanceof \App\Core\Ingestion\Data\ParsedRecord ? "{$i->indicatorKey}|{$i->entityCode}|{$i->period}|{$i->value}" : "{$i->indicatorKey}|{$i->sourceCode}>{$i->targetCode}|{$i->period}|{$i->value}", $items);

        $this->assertEqualsCanonicalizing([
            'imf.imts_exports|OMN|2026-06|3000', 'imf.imts_imports|OMN|2026-06|5000', 'imf.imts_exports_partner|OMN>CHN|2026-06|120', 'imf.imts_balance|OMN|2026-06|-2000',
        ], $records);
    }

    public function test_frankfurter_reads_daily_rates_and_skips_unknown_currencies(): void
    {
        $this->freshHttp();
        Http::fake(fn () => Http::response(['rates' => ['2026-10-16' => ['INR' => 94.96, 'XXX' => 1.0], 'bad' => ['INR' => 1]]]));
        $c = new \App\Modules\Markets\Connectors\FrankfurterFxConnector;

        $records = $this->records($c->parse($c->fetch()));

        $this->assertSame(['fx.usd_inr|USA|2026-10-16|94.96'], $records);
    }

    public function test_fao_production_parses_item_element_pairs_and_ignores_unknown_ones(): void
    {
        $c = new \App\Modules\Fao\Connectors\FaoProductionConnector;
        $payload = new \App\Core\Ingestion\Data\RawPayload(json_encode([['577|5510', 'OMN', '2024', '394946'], ['999|5510', 'OMN', '2024', '1'], ['1126|5111', 'OMN', '2023', '300000']]));

        $this->assertSame(['fao.prod_dates|OMN|2024|394946', 'fao.stock_camels|OMN|2023|300000'], $this->records($c->parse($payload)));
    }

    public function test_imf_cpi_reads_monthly_periods_for_the_chosen_categories_only(): void
    {
        $this->freshHttp();
        Http::fake(fn () => Http::response(implode("\n", [
            'DATAFLOW,COUNTRY,INDEX_TYPE,COICOP_1999,TYPE_OF_TRANSFORMATION,FREQUENCY,TIME_PERIOD,OBS_VALUE',
            'x,OMN,CPI,_T,YOY_PCH_PA_PT,M,2026-M08,1.5',
            'x,OMN,CPI,CP01,YOY_PCH_PA_PT,M,2026-M08,7.0',
            'x,OMN,CPI,CP05,YOY_PCH_PA_PT,M,2026-M08,2.0',
            'x,SAU,CPI,_T,IX,M,2026-M07,110.5',
            'x,SAU,CPI,_T,IX,A,2026,110.5',
            'x,OMN,CPI,_T,IX,M,2026-M08,',
        ])."\n"));
        $c = new ImfCpiConnector;

        $records = $this->records($c->parse($c->fetch()));

        $this->assertEqualsCanonicalizing(['imf.cpi_yoy|OMN|2026-08|1.5', 'imf.cpi_yoy_food|OMN|2026-08|7', 'imf.cpi_index|SAU|2026-07|110.5'], $records);
    }

    public function test_imf_sdmx_connectors_keep_countries_only_and_drop_far_forecasts(): void
    {
        $this->freshHttp();
        $future = (int) date('Y') + 3;
        Http::fake(fn () => Http::response(implode("\n", [
            'DATAFLOW,COUNTRY,SUB_TYPE,TIME_PERIOD,OBS_VALUE',
            'x,OMN,EX,2024,3.63',
            'x,OMN,IM,2024,7.99',
            "x,OMN,EX,{$future},2.1",
            'x,G001,EX,2024,1',
            'x,OMN,ZZ,2024,1',
            'x,SAU,EX,2024,',
        ])."\n"));
        $c = new ImfFossilSubsidiesConnector;

        $records = $this->records($c->parse($c->fetch()));

        $this->assertEqualsCanonicalizing(['imf.ffs_explicit|OMN|2024|3.63', 'imf.ffs_implicit|OMN|2024|7.99'], $records);
    }

    public function test_owid_keeps_iso3_countries_and_skips_regional_aggregates(): void
    {
        $this->freshHttp();
        Http::fake(fn () => Http::response("entity,code,year,v\nOman,OMN,2025,4.5\nASEAN (Ember),,2025,19\nWorld,OWID_WRL,2025,30\nOman,OMN,2024,\n"));
        $c = new OwidEnergyConnector;

        $records = array_unique($this->records($c->parse($c->fetch())));

        $this->assertContains('owid.renewable_electricity_share|OMN|2025|4.5', $records);
        $this->assertEmpty(array_filter($records, fn ($r) => str_contains($r, 'WRL') || str_contains($r, '2024')));
    }

    public function test_fao_reads_the_zipped_csv_maps_m49_to_iso3_and_assigns_three_year_averages_to_their_last_year(): void
    {
        $this->freshHttp();
        $csv = implode("\n", [
            '"Area Code","Area Code (M49)","Area","Item Code","Item","Element Code","Element","Year Code","Year","Unit","Value","Flag","Note"',
            '"221","\'512","Oman","21035","Cereal import dependency","6121","Value","1","2021-2023","%","88.6","E",',
            '"221","\'512","Oman","21025","Stunting","6121","Value","1","2024","%","12.9","E",',
            '"221","\'512","Oman","21025","Stunting","6121","Value","1","2023","%","<0.1","E",',
            '"221","\'512","Oman","99999","Other","6121","Value","1","2024","%","5","E",',
            '"5100","\'002","Africa","21035","Cereal","6121","Value","1","2021-2023","%","50","E",',
        ])."\n";
        $zip = tempnam(sys_get_temp_dir(), 'z');
        $archive = new \ZipArchive;
        $archive->open($zip, \ZipArchive::OVERWRITE);
        $archive->addFromString('Food_Security_Data_E_All_Data_(Normalized).csv', $csv);
        $archive->close();
        $reporters = array_map(fn (int $i) => ['reporterCode' => 1000 + $i, 'reporterCodeIsoAlpha3' => 'C'.$i, 'isGroup' => false], range(1, 160));
        $reporters[] = ['reporterCode' => 512, 'reporterCodeIsoAlpha3' => 'OMN', 'isGroup' => false];
        Http::fake(fn (Request $r) => str_contains($r->url(), 'Reporters.json') ? Http::response(['results' => $reporters]) : Http::response((string) file_get_contents($zip), 200, ['Content-Type' => 'application/zip']));
        $c = new FaoFoodSecurityConnector;

        $records = $this->records($c->parse($c->fetch()));
        @unlink($zip);

        $this->assertEqualsCanonicalizing(['fao.cereal_import_dependency|OMN|2023|88.6', 'fao.child_stunting|OMN|2024|12.9'], $records);
    }

    public function test_imf_iip_converts_to_billions_and_derives_the_net_position(): void
    {
        $this->freshHttp();
        Http::fake(fn () => Http::response(implode("\n", [
            'DATAFLOW,COUNTRY,BOP_ACCOUNTING_ENTRY,TIME_PERIOD,OBS_VALUE',
            'x,OMN,A_P,2024,114433768282',
            'x,OMN,L_P,2024,142320621399',
            'x,OMN,L_P,2023,100000000000',
        ])."\n"));
        $c = new ImfIipConnector;

        $records = $this->records($c->parse($c->fetch()));

        $this->assertContains('imf.iip_assets|OMN|2024|114.433768282', $records);
        $this->assertContains('imf.iip_net|OMN|2024|-27.886853117', $records);
        $this->assertEmpty(array_filter($records, fn ($r) => str_contains($r, 'iip_net|OMN|2023')), 'لا صافي بلا أصول وخصوم معًا');
    }

    public function test_comtrade_products_become_oman_to_hs_chapter_relations_with_arabic_names(): void
    {
        $this->freshHttp();
        Http::fake(function (Request $r) {
            $isOman = str_contains($r->url(), 'reporterCode=512');
            $isExport = str_contains($r->url(), 'flowCode=X');

            return Http::response(['data' => $isOman && $isExport ? [
                ['cmdCode' => '27', 'primaryValue' => 4.2e10],
                ['cmdCode' => 'TOTAL', 'primaryValue' => 9e10],
                ['cmdCode' => '26', 'primaryValue' => 0],
            ] : []]);
        });
        config(['omanilab.ingest.comtrade_throttle_microseconds' => 0]);
        $c = new ComtradeProductsConnector;

        $parsed = iterator_to_array($c->parse($c->fetch()), false);

        $rel = collect($parsed)->filter(fn ($i) => $i instanceof ParsedRelation)->map(fn ($i) => "{$i->indicatorKey}|{$i->sourceCode}>{$i->targetCode}|{$i->period}|{$i->value}")->unique()->values()->all();
        $names = collect($parsed)->filter(fn ($i) => $i instanceof ParsedEntity)->pluck('nameAr', 'code')->all();
        $this->assertContains('comtrade.hs_exports|OMN>HS27|'.((int) date('Y') - 1).'|42000000000', $rel);
        $this->assertCount(3, $rel, 'سنة لكل فصل فقط؛ الإجمالي والفصول الصفرية تُتخطى');
        $this->assertSame('وقود معدني وزيوت (نفط وغاز)', $names['HS27']);
    }

    public function test_unesco_keeps_iso3_countries_and_skips_regional_groups(): void
    {
        $this->freshHttp();
        Http::fake(fn () => Http::response(['records' => [
            ['geoUnit' => 'OMN', 'year' => 2024, 'value' => 42.2],
            ['geoUnit' => 'SDG: Sub-Saharan Africa', 'year' => 2024, 'value' => 9.0],
            ['geoUnit' => 'OMN', 'year' => 2023, 'value' => null],
        ]]));
        $c = new UnescoUisConnector;

        $records = array_unique($this->records($c->parse($c->fetch())));

        $this->assertContains('unesco.ger_tertiary|OMN|2024|42.2', $records);
        $this->assertEmpty(array_filter($records, fn ($r) => str_contains($r, 'SDG') || str_contains($r, '2023')));
    }

    public function test_oec_eci_takes_the_iso3_from_the_continent_prefixed_id(): void
    {
        $this->freshHttp();
        $rows = array_map(fn (int $i) => ['Country ID' => 'asxx'.chr(97 + $i % 26), 'Year' => 2020, 'ECI' => 0.1], range(1, 600));
        $rows[] = ['Country ID' => 'asomn', 'Year' => 2024, 'ECI' => -0.325];
        $rows[] = ['Country ID' => 'asomn', 'Year' => 2023, 'ECI' => null];
        Http::fake(fn () => Http::response(['data' => $rows]));
        $c = new OecComplexityConnector;

        $records = $this->records($c->parse($c->fetch()));

        $this->assertContains('oec.eci|OMN|2024|-0.325', $records);
        $this->assertEmpty(array_filter($records, fn ($r) => str_contains($r, '|2023|')));
    }

    public function test_oec_products_become_country_to_hs4_relations_with_the_last_four_digits_as_code(): void
    {
        $this->freshHttp();
        config(['omanilab.ingest.oec_throttle_microseconds' => 0]);
        Http::fake(fn (Request $r) => Http::response(['data' => str_contains($r->url(), 'asomn') ? [
            ['HS4 ID' => 52709, 'HS4' => 'Crude Petroleum', 'Year' => 2023, 'Trade Value' => 2.9e10],
            ['HS4 ID' => 63102, 'HS4' => 'Nitrogenous Fertilizers', 'Year' => 2023, 'Trade Value' => 2.6e9],
            ['HS4 ID' => 99999, 'HS4' => 'Zero', 'Year' => 2023, 'Trade Value' => 0],
        ] : []]));
        $c = new OecProductsConnector;

        $parsed = iterator_to_array($c->parse($c->fetch()), false);

        $rel = array_unique(array_map(fn ($i) => "{$i->indicatorKey}|{$i->sourceCode}>{$i->targetCode}|{$i->value}", array_filter($parsed, fn ($i) => $i instanceof ParsedRelation)));
        $this->assertContains('oec.hs4_exports|OMN>HS2709|29000000000', $rel);
        $this->assertContains('oec.hs4_exports|OMN>HS3102|2600000000', $rel);
        $this->assertCount(2, $rel);
    }

    public function test_eia_weeks_become_daily_points_and_months_average_only_when_complete(): void
    {
        $this->freshHttp();
        $week = fn (string $label, array $cells) => '<tr><td>&nbsp;&nbsp;'.$label.'</td>'.implode('', array_map(fn ($c) => "<td>{$c}</td>", $cells)).'</tr>';
        $html = '<table>'.$week('2026 Jan- 5 to Jan- 9', ['10.0', '20.0', '30.0', '40.0', '50.0']).$week('2026 Jan-12 to Jan-16', ['60.0', '', '', '', '']).$week('2026 Jan-26 to Jan-30', ['5.0', '5.0', '5.0', '5.0', '5.0']).'</table>';
        $pad = '';
        for ($i = 0; $i < 220; $i++) {
            $pad .= $week('1990 Jan- 1 to Jan- 5', ['1.0', '1.0', '1.0', '1.0', '1.0']);
        }
        Http::fake(fn () => Http::response($html.$pad));
        $c = new EiaSpotPricesConnector;

        $records = array_unique($this->records($c->parse($c->fetch())));

        $this->assertContains('eia.brent_d|WLD|2026-01-05|10', $records);
        $this->assertContains('eia.brent_d|WLD|2026-01-09|50', $records);
        $this->assertContains('eia.brent_d|WLD|2026-01-12|60', $records);
        $this->assertContains('eia.brent_m|WLD|1990-01|1', $records);
    }

    public function test_the_pink_sheet_link_is_discovered_on_the_page_and_its_monthly_sheet_is_read_from_the_xlsx(): void
    {
        $this->freshHttp();
        $strings = ['Crude oil, Brent', 'Crude oil, Dubai', '($/bbl)', '1960M01', '2025M12', 'Other'];
        $row = fn (int $r, array $cells) => "<row r=\"{$r}\">".implode('', array_map(fn ($c) => $c[0] === 's' ? "<c r=\"{$c[1]}{$r}\" t=\"s\"><v>{$c[2]}</v></c>" : "<c r=\"{$c[1]}{$r}\"><v>{$c[2]}</v></c>", $cells)).'</row>';
        $sheet = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .$row(5, [['s', 'B', 0], ['s', 'C', 1]])
            .$row(7, [['s', 'A', 3], ['n', 'B', '1.63'], ['n', 'C', '…']])
            .$row(8, [['s', 'A', 4], ['n', 'B', '62.72'], ['n', 'C', '61.98']])
            .'</sheetData></worksheet>';
        $zipPath = tempnam(sys_get_temp_dir(), 'x');
        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::OVERWRITE);
        $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Monthly Prices" sheetId="2" r:id="rId2"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId2" Target="worksheets/sheet2.xml"/></Relationships>');
        $zip->addFromString('xl/sharedStrings.xml', '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.implode('', array_map(fn ($s) => "<si><t>{$s}</t></si>", $strings)).'</sst>');
        $zip->addFromString('xl/worksheets/sheet2.xml', $sheet);
        $zip->close();
        $file = (string) file_get_contents($zipPath);
        @unlink($zipPath);
        Http::fake(fn (Request $r) => str_contains($r->url(), 'commodity-markets')
            ? Http::response('<a href="https://thedocs.worldbank.org/en/doc/abc-0050012026/related/CMO-Historical-Data-Monthly.xlsx">x</a>')
            : Http::response($file, 200, ['Last-Modified' => 'Tue, 06 Oct 2026 10:00:00 GMT']));
        $c = new WorldBankPinkSheetConnector;

        $probe = $c->probe();
        $records = $this->records($c->parse($c->fetch()));

        $this->assertStringContainsString('abc-0050012026', (string) $probe->token);
        $this->assertSame('2026-10-06', $probe->publishedAt?->toDateString());
        $this->assertEqualsCanonicalizing(['wbpink.crude_brent|WLD|1960-01|1.63', 'wbpink.crude_brent|WLD|2025-12|62.72', 'wbpink.crude_dubai|WLD|2025-12|61.98'], array_values(array_unique($records)));
    }

    public function test_ilo_scales_thousands_and_keeps_only_three_letter_country_codes(): void
    {
        $this->freshHttp();
        $csv = fn (string $measure, string $rows) => "DATAFLOW,REF_AREA,FREQ,MEASURE,TIME_PERIOD,OBS_VALUE\n{$rows}\n";
        Http::fake(function (Request $r) use ($csv) {
            return match (true) {
                str_contains($r->url(), 'EMP_TEMP_NB') && str_contains($r->url(), 'CCT_CIT_CITIZEN') => Http::response($csv('e', "x,OMN,A,EMP,2024,858.603\nx,OMN,A,EMP,2023,")),
                str_contains($r->url(), 'DF_UNE_DEAP_SEX_AGE_RT') && str_contains($r->url(), 'YGE15') => Http::response($csv('u', "x,SAU,A,UNE,2023,3.5\nx,X01,A,UNE,2023,9\nx,SAU,A,UNE,23,3")),
                default => Http::response($csv('d', '')),
            };
        });
        $c = new IloLabourConnector;

        $records = $this->records($c->parse($c->fetch()));

        $this->assertContains('ilo.emp_citizen|OMN|2024|858603', $records);
        $this->assertContains('ilo.unemployment|SAU|2023|3.5', $records);
        $this->assertCount(2, $records);
    }

    public function test_who_skips_future_estimates_and_non_country_codes(): void
    {
        $this->freshHttp();
        $future = (int) date('Y') + 5;
        Http::fake(fn () => Http::response(['value' => [
            ['SpatialDim' => 'OMN', 'TimeDim' => 2021, 'NumericValue' => 66.1],
            ['SpatialDim' => 'OMN', 'TimeDim' => $future, 'NumericValue' => 70],
            ['SpatialDim' => 'GLOBAL', 'TimeDim' => 2021, 'NumericValue' => 50],
            ['SpatialDim' => 'OMN', 'TimeDim' => 2020, 'NumericValue' => null],
        ]]));
        $c = new WhoHealthConnector;

        $records = array_unique($this->records($c->parse($c->fetch())));

        $this->assertContains('who.hale|OMN|2021|66.1', $records);
        $this->assertEmpty(array_filter($records, fn ($r) => str_contains($r, 'GLOBAL') || str_contains($r, (string) $future) || str_contains($r, '2020')));
    }

    public function test_every_new_indicator_has_validation_rules_and_unique_keys(): void
    {
        foreach ([new ImfCommodityPricesConnector, new UsTreasuryYieldsConnector, new IloLabourConnector, new WhoHealthConnector, new OwidIndicesConnector, new ImfFossilSubsidiesConnector, new ImfNdGainConnector] as $c) {
            foreach ($c->definition()->indicators as $i) {
                $this->assertNotEmpty($i->rules, $i->key);
            }
        }
    }
}
