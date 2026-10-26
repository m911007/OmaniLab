<?php

namespace Tests\Feature;

use App\Core\Ingestion\Data\ParsedAttribute;
use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\ParsedRelation;
use App\Core\Ingestion\Data\RawPayload;
use App\Modules\Bis\Connectors\BisStatisticsConnector;
use App\Modules\Boe\Connectors\BoeUkConnector;
use App\Modules\Climate\Connectors\GlobalClimateConnector;
use App\Modules\Climate\Connectors\OpenMeteoClimateConnector;
use App\Modules\Climate\Connectors\OpenMeteoExtraConnector;
use App\Modules\Climate\Connectors\OpenMeteoMarineConnector;
use App\Modules\Climate\Connectors\UsgsEarthquakesConnector;
use App\Modules\Climate\Connectors\WorldBankClimateConnector;
use App\Modules\Comtrade\Connectors\ComtradeProductsConnector;
use App\Modules\Ecb\Connectors\EcbEuroAreaConnector;
use App\Modules\Ember\Connectors\EmberElectricityConnector;
use App\Modules\Eurostat\Connectors\EurostatConnector;
use App\Modules\Fao\Connectors\FaoFoodBalanceConnector;
use App\Modules\Fao\Connectors\FaoFoodPriceIndexConnector;
use App\Modules\Fao\Connectors\FaoFoodSecurityConnector;
use App\Modules\Fao\Connectors\FaoProductionConnector;
use App\Modules\GlobalRates\Connectors\BankOfCanadaConnector;
use App\Modules\GlobalRates\Connectors\MofJapanJgbConnector;
use App\Modules\Ilo\Connectors\IloLabourConnector;
use App\Modules\Irena\Connectors\IrenaElectricityConnector;
use App\Modules\Macro\Connectors\ImfCoferConnector;
use App\Modules\Macro\Connectors\ImfCpiConnector;
use App\Modules\Macro\Connectors\ImfFossilSubsidiesConnector;
use App\Modules\Macro\Connectors\ImfIipConnector;
use App\Modules\Macro\Connectors\ImfMonthlyTradeConnector;
use App\Modules\Macro\Connectors\ImfNdGainConnector;
use App\Modules\Markets\Connectors\BlsUsEconomyConnector;
use App\Modules\Markets\Connectors\CboeIndicesConnector;
use App\Modules\Markets\Connectors\EiaSpotPricesConnector;
use App\Modules\Markets\Connectors\EiaWeeklyConnector;
use App\Modules\Markets\Connectors\FrankfurterFxConnector;
use App\Modules\Markets\Connectors\ImfCommodityPricesConnector;
use App\Modules\Markets\Connectors\NyFedRatesConnector;
use App\Modules\Markets\Connectors\UsTreasuryFiscalConnector;
use App\Modules\Markets\Connectors\UsTreasuryYieldsConnector;
use App\Modules\Markets\Connectors\WorldBankPinkSheetConnector;
use App\Modules\Oec\Connectors\OecComplexityConnector;
use App\Modules\Oec\Connectors\OecProductsConnector;
use App\Modules\Oecd\Connectors\OecdLeadingIndicatorsConnector;
use App\Modules\Ons\Connectors\OnsUkConnector;
use App\Modules\Owid\Connectors\OwidEnergyConnector;
use App\Modules\Owid\Connectors\OwidFossilFuelsConnector;
use App\Modules\Owid\Connectors\OwidIndicesConnector;
use App\Modules\PortWatch\Connectors\PortWatchConnector;
use App\Modules\Startups\Connectors\OmanStartupHubConnector;
use App\Modules\Unesco\Connectors\UnescoUisConnector;
use App\Modules\Unicef\Connectors\UnicefMortalityConnector;
use App\Modules\Who\Connectors\WhoHealthConnector;
use App\Modules\Wmd\Connectors\WorldMiningDataConnector;
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

        $this->assertEqualsCanonicalizing([
            'us.yield_3m|USA|2026-10-14|4.28', 'us.yield_2y|USA|2026-10-14|4.82', 'us.yield_10y|USA|2026-10-14|5.19',
            'us.spread_10y_2y|USA|2026-10-14|0.37', 'us.spread_10y_3m|USA|2026-10-14|0.91',
        ], array_values($records));
    }

    public function test_ecb_series_parse_daily_monthly_and_quarterly_periods(): void
    {
        $this->freshHttp();
        Http::fake(fn () => Http::response("KEY,FREQ,TIME_PERIOD,OBS_VALUE,OBS_STATUS\nX,D,2026-10-14,2.5,A\nX,M,2026-09,2.1,A\nX,Q,2026-Q1,0.5,A\nX,D,2026-10-15,,A\n"));
        $c = new EcbEuroAreaConnector;

        $records = array_unique($this->records($c->parse($c->fetch())));

        $this->assertContains('ecb.deposit_rate|EMU|2026-10-14|2.5', $records);
        $this->assertContains('ecb.hicp|EMU|2026-09|2.1', $records);
        $this->assertContains('ecb.gdp_growth|EMU|2026-Q1|0.5', $records);
        $this->assertNotContains('ecb.hicp|EMU|2026-10-15|', $records);
    }

    public function test_boe_series_convert_british_dates(): void
    {
        $this->freshHttp();
        Http::fake(fn () => Http::response("DATE,IUDBEDR\n02 Oct 2026,3.75\n05 Oct 2026,3.5\n"));
        $c = new BoeUkConnector;

        $records = array_unique($this->records($c->parse($c->fetch())));

        $this->assertContains('boe.bank_rate|GBR|2026-10-02|3.75', $records);
        $this->assertContains('boe.gilt_10y|GBR|2026-10-05|3.5', $records);
    }

    public function test_fao_food_price_index_skips_preamble_and_blank_columns(): void
    {
        $this->freshHttp();
        Http::fake(fn () => Http::response("FAO Food Price Index,,\n2014-2016=100,,\nDate,Food Price Index,Meat,Dairy,Cereals,Oils,Sugar,,\n,,,\n2026-09,136.0,127.9,119.1,122.8,198.6,,,\n"));
        $c = new FaoFoodPriceIndexConnector;

        $records = array_unique($this->records($c->parse($c->fetch())));

        $this->assertContains('fao.ffpi|WLD|2026-09|136', $records);
        $this->assertContains('fao.ffpi_oils|WLD|2026-09|198.6', $records);
        $this->assertCount(5, $records);
    }

    public function test_ember_keeps_country_rows_of_wanted_variables_only(): void
    {
        $this->freshHttp();
        $csv = "Area,ISO 3 code,Year,Area type,Category,Subcategory,Variable,Unit,Value\n"
            ."Oman,OMN,2024,Country,Electricity generation,Fuel,Gas,TWh,45.5\n"
            ."Oman,OMN,2024,Country,Electricity generation,Fuel,Gas,%,95\n"
            ."World,,2024,World,Electricity generation,Fuel,Gas,TWh,9000\n"
            ."Oman,OMN,2024,Country,Capacity,Fuel,Solar,GW,0.5\n"
            ."Oman,OMN,2024,Country,Something else,Fuel,Gas,TWh,1\n";
        Http::fake(fn () => Http::response($csv));
        $c = new EmberElectricityConnector;

        $records = array_unique($this->records($c->parse($c->fetch())));

        $this->assertEqualsCanonicalizing(['ember.gen_gas|OMN|2024|45.5', 'ember.share_gas|OMN|2024|95', 'ember.capacity_solar|OMN|2024|0.5'], array_values($records));
    }

    public function test_oecd_keeps_amplitude_adjusted_monthly_index_rows(): void
    {
        $this->freshHttp();
        $csv = "REF_AREA,Reference area,FREQ,MEASURE,ADJUSTMENT,TRANSFORMATION,TIME_PERIOD,OBS_VALUE\n"
            ."USA,United States,M,LI,AA,IX,2026-09,99.5\n"
            ."USA,United States,M,LI,NOR,IX,2026-09,100.2\n"
            ."G7,G7,M,BCICP,AA,IX,2026-09,99.1\n"
            ."USA,United States,Q,LI,AA,IX,2026-Q3,99.9\n";
        Http::fake(fn () => Http::response($csv));
        $c = new OecdLeadingIndicatorsConnector;

        $records = array_unique($this->records($c->parse($c->fetch())));

        $this->assertEqualsCanonicalizing(['oecd.cli|USA|2026-09|99.5', 'oecd.business_confidence|G7|2026-09|99.1'], array_values($records));
    }

    public function test_open_meteo_extra_converts_units_and_publishes_complete_months_only(): void
    {
        $time = [];
        $solar = [];
        $sun = [];
        foreach (range(1, 30) as $d) {
            $time[] = sprintf('2026-06-%02d', $d);
            $solar[] = 36.0;
            $sun[] = 36000.0;
        }
        $payload = new RawPayload(json_encode(['last' => '2026-07-10', 'cities' => ['muscat' => [
            'daily' => ['time' => $time, 'shortwave_radiation_sum' => $solar, 'sunshine_duration' => $sun, 'wind_speed_10m_mean' => array_fill(0, 30, 10.0), 'relative_humidity_2m_mean' => array_fill(0, 30, 50), 'et0_fao_evapotranspiration' => array_fill(0, 30, 6.0)],
            'air' => ['pm10' => ['2026-06-01' => 50.0]],
        ]]]));

        $records = array_unique($this->records((new OpenMeteoExtraConnector)->parse($payload)));

        $this->assertContains('climate.solar_muscat|OMN|2026-06|10', $records);
        $this->assertContains('climate.sunshine_muscat|OMN|2026-06|10', $records);
        $this->assertContains('climate.et0_muscat|OMN|2026-06|180', $records);
        $this->assertCount(5, $records);
    }

    public function test_global_climate_parses_noaa_nasa_and_oni_formats(): void
    {
        $this->freshHttp();
        Http::fake([
            '*co2_mm_mlo*' => Http::response("# comment\nyear,month,decimal date,average,deseasonalized\n2026,9,2026.708,426.5,428.1\n2026,8,2026.62,-99.99,-99.99\n"),
            '*ch4_mm_gl*' => Http::response("# c\nyear,month,decimal,average\n2026,9,2026.7,1950.5\n"),
            '*GLB.Ts*' => Http::response("Land-Ocean: Global Means\nYear,Jan,Feb\n2026,1.20,***\n"),
            '*climate-at-a-glance*' => Http::response("# Title\nDate,Departure\n202609,1.35\n"),
            '*oni.ascii*' => Http::response(" SEAS  YR   TOTAL   ANOM\n  DJF 2026  26.1  -0.4\n"),
        ]);
        $c = new GlobalClimateConnector;

        $records = array_unique($this->records($c->parse($c->fetch())));

        $this->assertEqualsCanonicalizing([
            'climate.co2_ppm|WLD|2026-09|426.5', 'climate.ch4_ppb|WLD|2026-09|1950.5', 'climate.temp_anomaly_giss|WLD|2026-01|1.2',
            'climate.temp_anomaly_noaa|WLD|2026-09|1.35', 'climate.oni|WLD|2026-01|-0.4',
        ], array_values($records));
    }

    public function test_world_mining_data_reads_country_rows_and_skips_unknown_names(): void
    {
        $this->freshHttp();
        $path = tempnam(sys_get_temp_dir(), 'w').'.xlsx';
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE);
        $zip->addFromString('xl/workbook.xml', '<workbook><sheets><sheet name="Copper" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships><Relationship Id="rId1" Type="x" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/sharedStrings.xml', '<sst><si><t>Country</t></si><si><t>unit</t></si><si><t>Oman</t></si><si><t>metr. t</t></si><si><t>Atlantis</t></si></sst>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet><sheetData>'
            .'<row r="2"><c r="A2" t="s"><v>0</v></c><c r="B2" t="s"><v>1</v></c><c r="C2"><v>2023</v></c><c r="D2"><v>2024</v></c></row>'
            .'<row r="3"><c r="A3" t="s"><v>2</v></c><c r="B3" t="s"><v>3</v></c><c r="C3"><v>0</v></c><c r="D3"><v>2790</v></c></row>'
            .'<row r="4"><c r="A4" t="s"><v>4</v></c><c r="B4" t="s"><v>3</v></c><c r="C4"><v>1</v></c><c r="D4"><v>1</v></c></row>'
            .'</sheetData></worksheet>');
        $zip->close();
        $bytes = file_get_contents($path);
        unlink($path);
        Http::fake([
            '*wmd.html' => Http::response('<a href="https://www.bmf.gv.at/dam/jcr:1/6.4.%20Production.xlsx">x</a>'),
            '*6.4.*' => Http::response($bytes),
        ]);
        $c = new WorldMiningDataConnector;

        $records = array_unique($this->records($c->parse($c->fetch())));

        $this->assertEqualsCanonicalizing(['wmd.copper|OMN|2023|0', 'wmd.copper|OMN|2024|2790'], array_values($records));
    }

    public function test_eia_weekly_parses_year_month_rows_with_week_value_pairs(): void
    {
        $this->freshHttp();
        $rows = '';
        foreach (range(1990, 2026) as $y) {
            foreach (['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug'] as $mi => $mon) {
                $rows .= sprintf('<tr><td>%d-%s</td><td>&nbsp;</td><td>%02d/05</td><td>&nbsp;</td><td>334,786</td><td>%02d/12</td><td>&nbsp;</td><td>335,260.5</td></tr>', $y, $mon, $mi + 1, $mi + 1);
            }
        }
        Http::fake(fn () => Http::response("<table>{$rows}</table>"));
        $c = new EiaWeeklyConnector;

        $records = array_unique($this->records($c->parse($c->fetch())));

        $this->assertContains('eia.us_crude_stocks|USA|2026-08-05|334786', $records);
        $this->assertContains('eia.us_crude_stocks|USA|2026-08-12|335260.5', $records);
    }

    public function test_eurostat_maps_two_letter_geo_codes_and_aggregates(): void
    {
        $this->freshHttp();
        Http::fake(fn () => Http::response("DATAFLOW,LAST UPDATE,freq,unit,geo,TIME_PERIOD,OBS_VALUE,OBS_FLAG\nX,d,M,RCH_A,EL,2026-09,3.1,\nX,d,M,RCH_A,EU27_2020,2026-09,2.2,\nX,d,M,RCH_A,ZZ,2026-09,9.9,\nX,d,Q,RCH_A,DE,2026-Q1,1.5,\nX,d,M,RCH_A,DE,2026-09,,\n"));
        $c = new EurostatConnector;

        $records = array_unique($this->records($c->parse($c->fetch())));

        $this->assertContains('eurostat.hicp|GRC|2026-09|3.1', $records);
        $this->assertContains('eurostat.hicp|EU27|2026-09|2.2', $records);
        $this->assertContains('eurostat.hicp|DEU|2026-Q1|1.5', $records);
        $this->assertNotContains('eurostat.hicp|ZZ|2026-09|9.9', $records);
    }

    public function test_portwatch_reads_chokepoints_ports_and_country_totals(): void
    {
        $this->freshHttp();
        $f = fn (array $rows) => Http::response(['features' => array_map(fn ($a) => ['attributes' => $a], $rows)]);
        Http::fake([
            '*PortWatch_chokepoints_database*' => $f([['portid' => 'chokepoint6', 'portname' => 'Strait of Hormuz']]),
            '*PortWatch_ports_database*' => $f([['ISO3' => 'OMN', 'country' => 'Oman']]),
            '*Daily_Chokepoints_Data*' => $f([['date' => '2026-10-11', 'portid' => 'chokepoint6', 'n_total' => 100, 'n_tanker' => 40, 'capacity' => 5000000]]),
            '*Daily_Ports_Data*' => function ($request) use ($f) {
                return str_contains(urldecode($request->url()), "ISO3='OMN'")
                    ? $f([['portid' => 'port746', 'portname' => 'Salalah', 'year' => 2026, 'month' => 10, 'calls' => 49, 'imp' => 672740, 'exp' => 1122008]])
                    : $f([['ISO3' => 'OMN', 'year' => 2026, 'month' => 10, 'calls' => 120, 'imp' => 1, 'exp' => 2], ['ISO3' => '', 'year' => 2026, 'month' => 10, 'calls' => 1, 'imp' => 1, 'exp' => 1]]);
            },
        ]);
        $c = new PortWatchConnector;

        $records = array_unique($this->records($c->parse($c->fetch())));

        $this->assertContains('portwatch.chokepoint_transits|PW_chokepoint6|2026-10-11|100', $records);
        $this->assertContains('portwatch.chokepoint_tankers|PW_chokepoint6|2026-10-11|40', $records);
        $this->assertContains('portwatch.port_calls|PW_port746|2026-10|49', $records);
        $this->assertContains('portwatch.country_port_calls|OMN|2026-10|120', $records);
        $this->assertCount(3 + 3 + 3, $records);
    }

    public function test_open_meteo_marine_aggregates_sst_and_wave_peaks_per_month(): void
    {
        $time = [];
        foreach (range(1, 30) as $d) {
            $time[] = sprintf('2026-06-%02d', $d);
        }
        $waves = array_fill(0, 30, 1.0);
        $waves[5] = 4.5;
        $payload = new RawPayload(json_encode(['last' => '2026-07-10', 'points' => ['salalah' => [
            'time' => $time, 'wave_height_max' => $waves, 'wave_period_max' => array_fill(0, 30, 8.0), 'sea_surface_temperature_mean' => array_fill(0, 30, 27.0),
        ]]]));

        $records = array_unique($this->records((new OpenMeteoMarineConnector)->parse($payload)));

        $this->assertContains('marine.wave_peak_salalah|OMN|2026-06|4.5', $records);
        $this->assertContains('marine.sst_salalah|OMN|2026-06|27', $records);
        $this->assertContains('marine.wave_period_salalah|OMN|2026-06|8', $records);
    }

    public function test_ons_parses_month_and_quarter_labels(): void
    {
        $this->freshHttp();
        Http::fake(fn () => Http::response(['months' => [['date' => '2026 AUG', 'value' => '3.1'], ['date' => '2026 JUL', 'value' => '']], 'quarters' => [['date' => '2026 Q2', 'value' => '0.5']]]));
        $c = new OnsUkConnector;

        $records = array_unique($this->records($c->parse($c->fetch())));

        $this->assertContains('ons.cpi_yoy|GBR|2026-08|3.1', $records);
        $this->assertContains('ons.gdp_qoq|GBR|2026-Q2|0.5', $records);
        $this->assertNotContains('ons.cpi_yoy|GBR|2026-07|0', $records);
    }

    public function test_jgb_converts_slash_dates_and_boc_monthly_periods(): void
    {
        $this->freshHttp();
        Http::fake([
            '*jgbcme*' => Http::response("Interest Rate,,,(Unit : %)\nDate,1Y,2Y,5Y,10Y,20Y,30Y\n2026/10/2,0.9,1.0,1.3,1.9,2.6,3.1\n2026/10/3,-,1.0,1.3,1.9,2.6,3.1\n"),
            '*valet*' => Http::response(['observations' => [['d' => '2026-08-01', 'V39079' => ['v' => '2.25'], 'STATIC_ATABLE_V41690973' => ['v' => '3.0'], 'CPI_TRIM' => ['v' => '1.9'], 'FXUSDCAD' => ['v' => '1.41'], 'BD.CDN.2YR.DQ.YLD' => ['v' => '2.5'], 'BD.CDN.5YR.DQ.YLD' => ['v' => '2.7'], 'BD.CDN.10YR.DQ.YLD' => ['v' => '3.1']]]]),
        ]);

        $jp = array_unique($this->records((new MofJapanJgbConnector)->parse((new MofJapanJgbConnector)->fetch())));
        $c = new BankOfCanadaConnector;
        $ca = array_unique($this->records($c->parse($c->fetch())));

        $this->assertContains('jp.jgb_10y|JPN|2026-10-02|1.9', $jp);
        $this->assertNotContains('jp.jgb_1y|JPN|2026-10-03|0', $jp);
        $this->assertContains('ca.cpi_yoy|CAN|2026-08|3', $ca);
        $this->assertContains('ca.policy_rate|CAN|2026-08-01|2.25', $ca);
    }

    public function test_imf_cofer_parses_shares_and_values_for_world(): void
    {
        $this->freshHttp();
        $head = 'DATAFLOW,COUNTRY,INDICATOR,FXR_CURRENCY,TYPE_OF_TRANSFORMATION,FREQUENCY,TIME_PERIOD,OBS_VALUE';
        Http::fake(['*COFER*' => Http::response($head."\nIMF.STA:COFER(7.0.1),G001,AFXRA,CI_USD,SHRO_PT,Q,2026-Q2,56.7\nIMF.STA:COFER(7.0.1),G001,AFXRA,CI_USD,NV_USD,Q,2026-Q2,7494549610004\nIMF.STA:COFER(7.0.1),G001,AFXRA,CI_T,SHRO_PT,Q,2026-Q2,100\nIMF.STA:COFER(7.0.1),G001,AFXRA,CI_DEM,SHRO_PT,Q,2026-Q2,1\n")]);
        $c = new ImfCoferConnector;
        $r = array_unique($this->records($c->parse($c->fetch())));

        $this->assertContains('imf.cofer_share_usd|WLD|2026-Q2|56.7', $r);
        $this->assertContains('imf.cofer_value_usd|WLD|2026-Q2|7494.549610004', $r);
        $this->assertCount(2, array_filter($r, fn ($x) => str_starts_with($x, 'imf.cofer')));
    }

    public function test_world_bank_climate_parses_history_and_projections(): void
    {
        $this->freshHttp();
        Http::fake(['*cckpapi*' => fn ($req) => Http::response(['data' => [basename(parse_url($req->url(), PHP_URL_PATH)) => ['2024-07' => 28.65, '2100-07' => 31.2]]])]);
        $c = new WorldBankClimateConnector;
        $r = $this->records($c->parse($c->fetch()));

        $this->assertContains('climate.hist_tas|OMN|2024|28.65', $r);
        $this->assertContains('climate.proj_pr_ssp585|OMN|2100|31.2', $r);
    }

    public function test_startup_hub_parses_cards_and_company_pages(): void
    {
        $this->freshHttp();
        config(['omanilab.startups_snapshot' => '/nonexistent/snapshot.json']);
        $card = '<div class="card-startup"><div class="company-detail"><h5 class="mb-3"><a href="https://omanstartuphub.om/ar/startup_companies/raaz">Raaz</a></h5><p>Fintech app...</p><span class="badge">التمويل</span><span class="badge">مواهب</span></div></div>';
        $detail = '<div class="details-card-tag"> النمو الأولي</div><p class="text-white">Raaz is a personal finance platform for everyone.</p>'
            .'<h6>1-5</h6><div>عدد الموظفين</div><h6> فبراير, 2025 </h6><div>سنة التأسيس</div><h6>ش.م.م</h6><div>نوع الشركة</div>'
            .'<div class="gray-block w-100"><h5>القطاع</h5><span class="tag"> التقنية </span></div><div class="gray-block w-100"><h5>الخدمات</h5><span class="tag"> تطبيقات </span></div>';
        Http::fake([
            '*ar/startup_companies' => Http::response('<div class="counter fz-60" >224 </div><p class="text">شركة ناشئة وفي إزدياد</p>'),
            '*startups/content?page=1*' => Http::response($card),
            '*startups/content*' => Http::response(''),
            '*startup_companies/raaz*' => Http::response($detail),
        ]);
        $c = new OmanStartupHubConnector;

        $items = iterator_to_array($c->parse($c->fetch()), false);
        $attrs = [];
        foreach ($items as $i) {
            if ($i instanceof ParsedAttribute) {
                $attrs[$i->key] = $i->value;
            }
        }

        $this->assertSame('النمو الأولي', $attrs['stage']);
        $this->assertSame('1-5', $attrs['employees']);
        $this->assertSame(2025, $attrs['founded_year']);
        $this->assertSame(['التقنية'], $attrs['sector']);
        $this->assertSame(['التمويل', 'مواهب'], $attrs['seeking']);
        $records = array_values(array_filter($items, fn ($i) => $i instanceof ParsedRecord));
        $this->assertSame(['startups.platform_count', 224.0], [$records[0]->indicatorKey, $records[0]->value]);
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
        $c = new OpenMeteoClimateConnector;

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
        $records = array_map(fn ($i) => $i instanceof ParsedRecord ? "{$i->indicatorKey}|{$i->entityCode}|{$i->period}|{$i->value}" : "{$i->indicatorKey}|{$i->sourceCode}>{$i->targetCode}|{$i->period}|{$i->value}", $items);

        $this->assertEqualsCanonicalizing([
            'imf.imts_exports|OMN|2026-06|3000', 'imf.imts_imports|OMN|2026-06|5000', 'imf.imts_exports_partner|OMN>CHN|2026-06|120', 'imf.imts_balance|OMN|2026-06|-2000',
        ], $records);
    }

    public function test_frankfurter_reads_daily_rates_and_skips_unknown_currencies(): void
    {
        $this->freshHttp();
        Http::fake(fn () => Http::response(['rates' => ['2026-10-16' => ['INR' => 94.96, 'XXX' => 1.0], 'bad' => ['INR' => 1]]]));
        $c = new FrankfurterFxConnector;

        $records = $this->records($c->parse($c->fetch()));

        $this->assertSame(['fx.usd_inr|USA|2026-10-16|94.96'], $records);
    }

    public function test_fao_production_parses_item_element_pairs_and_ignores_unknown_ones(): void
    {
        $c = new FaoProductionConnector;
        $payload = new RawPayload(json_encode([['577|5510', 'OMN', '2024', '394946'], ['999|5510', 'OMN', '2024', '1'], ['1126|5111', 'OMN', '2023', '300000']]));

        $this->assertSame(['fao.prod_dates|OMN|2024|394946', 'fao.stock_camels|OMN|2023|300000'], $this->records($c->parse($payload)));
    }

    public function test_fao_food_balance_derives_self_sufficiency_from_production_and_supply(): void
    {
        $c = new FaoFoodBalanceConnector;
        $payload = new RawPayload(json_encode([
            ['2511', '5511', 'OMN', '2023', '8'], ['2511', '5301', 'OMN', '2023', '1463'], ['2511', '645', 'OMN', '2023', '55.19'],
            ['2960', '5511', 'OMN', '2023', '580'], ['2960', '5301', 'OMN', '2023', '285'],
            ['2901', '664', 'OMN', '2023', '3337'], ['9999', '5511', 'OMN', '2023', '1'],
            // إنتاج مُسنَد (I) من المنظمة: لا يُنشر ولا تُحسب منه نسبة اكتفاء
            ['2734', '5511', 'OMN', '2023', '7', 'I'], ['2734', '5301', 'OMN', '2023', '76', 'E'], ['2734', '5611', 'OMN', '2023', '101', 'A'],
        ]));

        $records = $this->records($c->parse($payload));

        $this->assertContains('fao.fbs.wheat.prod|OMN|2023|8', $records);
        $this->assertContains('fao.fbs.wheat.kgcap|OMN|2023|55.19', $records);
        $this->assertContains('fao.fbs.wheat.ssr|OMN|2023|0.55', $records);
        $this->assertContains('fao.fbs.fish.ssr|OMN|2023|203.51', $records);
        $this->assertContains('fao.fbs.kcal|OMN|2023|3337', $records);
        // إنتاج الدواجن مُسنَد: يُستبعد هو والمتاح المبني عليه، وتبقى الواردات
        $this->assertContains('fao.fbs.poultry.imp|OMN|2023|101', $records);
        $this->assertNotContains('fao.fbs.poultry.prod|OMN|2023|7', $records);
        $this->assertNotContains('fao.fbs.poultry.supply|OMN|2023|76', $records);
        $this->assertCount(9, $records);
    }

    public function test_owid_fossil_fuels_derive_familiar_units(): void
    {
        $c = new OwidFossilFuelsConnector;
        $payload = new RawPayload(json_encode(['owid.oil_production_twh' => [['OMN', '2025', 559.6964]], 'owid.oil_reserves_m3' => [['OMN', '2021', 854238800]], 'owid.gas_reserves_m3' => [['OMN', '2021', 651287555694]]]));

        $records = $this->records($c->parse($payload));

        $this->assertContains('owid.oil_production_kbd|OMN|2025|902', $records);
        $this->assertContains('owid.oil_reserves_bn_bbl|OMN|2021|5.37', $records);
        $this->assertContains('owid.gas_reserves_tcm|OMN|2021|0.651', $records);
    }

    public function test_irena_maps_country_names_to_iso_and_technology_to_indicator_keys(): void
    {
        $this->freshHttp();
        Http::fake(function ($request) {
            if ($request->method() === 'GET') {
                return Http::response(['variables' => [['code' => 'Country/area', 'values' => ['OMN', 'ARE'], 'valueTexts' => ['Oman', 'United Arab Emirates']]]]);
            }

            return Http::response("\"Country/area\",\"Technology\",\"Data Type\",\"Grid connection\",\"Year\",Electricity statistics\n\"Oman\",\"Solar photovoltaic\",\"Electrical Installed Capacity (MW)\",\"All\",\"2024\",600.5\n\"Oman\",\"Mystery\",\"Electrical Installed Capacity (MW)\",\"All\",\"2024\",1\n\"Narnia\",\"Oil\",\"Electricity Generation (GWh)\",\"All\",\"2024\",5\n");
        });
        $c = new IrenaElectricityConnector;

        $this->assertSame(['irena.cap.solar_pv|OMN|2024|600.5'], $this->records($c->parse($c->fetch())));
    }

    public function test_bis_maps_iso2_areas_and_skips_unknown_periods(): void
    {
        $this->freshHttp();
        $countries = [['page' => 1], array_map(fn ($i) => ['id' => 'C'.str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'iso2Code' => 'X'.chr(65 + $i % 26), 'region' => ['value' => 'Europe']], range(0, 25))];
        $countries[1][] = ['id' => 'SAU', 'iso2Code' => 'SA', 'region' => ['value' => 'Middle East']];
        for ($i = 0; $i < 140; $i++) {
            $countries[1][] = ['id' => 'D'.$i, 'iso2Code' => 'A'.$i, 'region' => ['value' => 'Asia']];
        }
        Http::fake(function ($request) use ($countries) {
            if (str_contains($request->url(), 'worldbank')) {
                return Http::response($countries);
            }

            return Http::response("STRUCTURE,REF_AREA,TIME_PERIOD,OBS_VALUE\ndataflow,SA,2026-09,4.5\ndataflow,ZZ,2026-09,9\ndataflow,SA,bad,1\n");
        });
        $c = new BisStatisticsConnector;

        $records = $this->records($c->parse($c->fetch()));

        $this->assertContains('bis.policy_rate|SAU|2026-09|4.5', $records);
        $this->assertNotContains('bis.policy_rate|SAU|bad|1', $records);
    }

    public function test_usgs_fills_empty_months_with_zero_and_keeps_the_strongest_magnitude(): void
    {
        $this->freshHttp();
        Http::fake(fn () => Http::response("time,latitude,longitude,depth,mag\n2000-01-05T00:00:00Z,20,55,10,3.2\n2000-01-20T00:00:00Z,21,56,10,4.1\n2000-03-02T00:00:00Z,22,57,10,3.0\n"));
        $c = new UsgsEarthquakesConnector;

        $records = $this->records($c->parse($c->fetch()));

        $this->assertContains('hazard.quakes_m3_count|OMN|2000-01|2', $records);
        $this->assertContains('hazard.quakes_m3_count|OMN|2000-02|0', $records);
        $this->assertContains('hazard.quakes_max_magnitude|OMN|2000-01|4.1', $records);
        $this->assertNotContains('hazard.quakes_max_magnitude|OMN|2000-02|0', $records);
    }

    public function test_unicef_keeps_country_rows_for_known_indicators(): void
    {
        $this->freshHttp();
        Http::fake(fn () => Http::response("STRUCTURE,REF_AREA,INDICATOR,TIME_PERIOD,OBS_VALUE\nd,OMN,CME_MRY0T4,2023,10.39\nd,AUREC_EAC,CME_MRY0T4,2023,50\nd,OMN,OTHER,2023,1\nd,OMN,CME_MRM0,x,3\n"));
        $c = new UnicefMortalityConnector;

        $this->assertSame(['unicef.under5_mortality|OMN|2023|10.39'], $this->records($c->parse($c->fetch())));
    }

    public function test_us_market_connectors_parse_their_sources(): void
    {
        $this->freshHttp();
        Http::fake(function ($request) {
            $u = $request->url();
            if (str_contains($u, 'cdn.cboe.com') && str_contains($u, 'DJX')) {
                return Http::response("DATE,DJX\n10/16/2026,520.02\n");
            }
            if (str_contains($u, 'cdn.cboe.com') && str_contains($u, 'VIX')) {
                return Http::response("DATE,OPEN,HIGH,LOW,CLOSE\n10/16/2026,16.02,22.31,15.72,19.99\n");
            }
            if (str_contains($u, 'cdn.cboe.com')) {
                return Http::response("DATE,SPX\n10/16/2026,7659.44\n");
            }
            if (str_contains($u, 'newyorkfed')) {
                return Http::response(['refRates' => [['effectiveDate' => '2026-10-15', 'type' => 'EFFR', 'percentRate' => 3.88, 'targetRateFrom' => 3.75, 'targetRateTo' => 4.0]]]);
            }
            if (str_contains($u, 'bls.gov')) {
                return Http::response(['status' => 'REQUEST_SUCCEEDED', 'Results' => ['series' => [['seriesID' => 'CUUR0000SA0', 'data' => [['year' => '2026', 'period' => 'M09', 'value' => '336.94'], ['year' => '2025', 'period' => 'M09', 'value' => '321.0'], ['year' => '2026', 'period' => 'M13', 'value' => '1']]]]]]);
            }

            return Http::response(['data' => [['record_date' => '2026-10-15', 'tot_pub_debt_out_amt' => '40388317675071.91', 'security_desc' => 'Treasury Bills', 'avg_interest_rate_amt' => '3.870', 'classification_desc' => 'Customs Duties', 'current_month_gross_rcpt_amt' => '25271564478.23']], 'meta' => ['total-pages' => 1]]);
        });

        $cboe = $this->records((new CboeIndicesConnector)->parse((new CboeIndicesConnector)->fetch()));
        $fed = $this->records((new NyFedRatesConnector)->parse((new NyFedRatesConnector)->fetch()));
        $bls = $this->records((new BlsUsEconomyConnector)->parse((new BlsUsEconomyConnector)->fetch()));
        $fiscal = $this->records((new UsTreasuryFiscalConnector)->parse((new UsTreasuryFiscalConnector)->fetch()));

        $this->assertContains('us.djia|USA|2026-10-16|52002', $cboe);
        $this->assertContains('us.vix_high|USA|2026-10-16|22.31', $cboe);
        $this->assertContains('us.fed_target_upper|USA|2026-10-15|4', $fed);
        $this->assertContains('us.cpi_yoy|USA|2026-09|4.97', $bls);
        $this->assertContains('us.public_debt|USA|2026-10-15|40388317675072', $fiscal);
        $this->assertContains('us.receipts_customs|USA|2026-10|25271564478.23', $fiscal);
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
