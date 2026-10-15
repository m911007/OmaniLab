<?php

namespace Tests\Feature;

use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Modules\Manafeth\Connectors\ManafethPartnersConnector;
use App\Modules\Manafeth\Connectors\ManafethTotalsConnector;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ManafethConnectorTest extends TestCase
{
    private const API = 'https://manafeth.ncsi.gov.om/api/Main';

    private const UN = 'https://comtradeapi.un.org/files/v1/app/reference/Reporters.json';

    private const UN_PARTNERS = 'https://comtradeapi.un.org/files/v1/app/reference/partnerAreas.json';

    private function fakeStartYear(int $year, int $month): void
    {
        Http::fake([self::API.'/GetStatisticsStartYear' => Http::response(['DataObject' => [['YEAR' => $year, 'MONTH' => (string) $month]]])]);
    }

    /**
     * @param  iterable<mixed>  $items
     * @return list<ParsedRecord>
     */
    private function records(iterable $items): array
    {
        return array_values(array_filter(iterator_to_array($items, false), fn ($i) => $i instanceof ParsedRecord));
    }

    public function test_probe_reports_the_latest_published_month(): void
    {
        $this->fakeStartYear(2026, 7);

        $p = (new ManafethTotalsConnector)->probe();

        $this->assertSame('2026-7', $p->token);
        $this->assertSame('2026-07-31', $p->publishedAt->toDateString());
        $this->assertStringContainsString('2026-07', $p->version);
    }

    public function test_totals_keep_complete_years_annual_and_publish_the_partial_year_as_year_to_date_only(): void
    {
        $this->freshHttp();
        $this->fakeStartYear(2026, 7);
        Http::fake([self::API.'/GetStatistics' => Http::response(['DataObject' => [
            ['year' => '2025', 'import_value' => '17161072514', 'export_value' => '21208630443', 'reexport_value' => '2055765812', 'totalimport_by_sea' => '11005704057', 'totalimport_by_post' => '3977100405'],
            ['year' => '2026', 'import_value' => '10408555305', 'export_value' => '14725623953', 'reexport_value' => null, 'totalimport_by_sea' => '7000000000'],
        ]])]);
        $c = new ManafethTotalsConnector;

        $by = collect($this->records($c->parse($c->fetch())))->mapWithKeys(fn ($r) => ["{$r->indicatorKey}|{$r->period}" => $r->value])->all();

        $this->assertSame(17161072514.0, $by['manafeth.IMPORTS|2025']);
        $this->assertSame(11005704057.0, $by['manafeth.IMPORTS_SEA|2025']);
        $this->assertSame(3977100405.0, $by['manafeth.IMPORTS_LAND|2025'], 'by_post في المصدر هو المنافذ البرية');
        $this->assertArrayNotHasKey('manafeth.IMPORTS|2026', $by, 'السنة الجزئية لا تظهر كسنة كاملة');
        $this->assertSame(10408555305.0, $by['manafeth.IMPORTS_YTD|2026']);
        $this->assertArrayNotHasKey('manafeth.IMPORTS_SEA|2026', $by, 'تفاصيل النقل للسنة الجزئية لا تُنشر');
        $this->assertArrayNotHasKey('manafeth.REEXPORTS_YTD|2026', $by, 'القيمة الفارغة تُتخطى');
    }

    public function test_in_december_the_latest_year_is_complete_and_annual(): void
    {
        $this->freshHttp();
        $this->fakeStartYear(2025, 12);
        Http::fake([self::API.'/GetStatistics' => Http::response(['DataObject' => [['year' => '2025', 'import_value' => '17161072514']]])]);
        $c = new ManafethTotalsConnector;

        $keys = array_map(fn ($r) => $r->indicatorKey, $this->records($c->parse($c->fetch())));

        $this->assertSame(['manafeth.IMPORTS'], $keys);
    }

    public function test_partners_aggregate_ports_map_m49_to_iso3_and_drop_zeros(): void
    {
        $this->freshHttp();
        $this->fakeStartYear(2026, 7);
        $country = fn (int $id, string $port, string $v, string $name) => ['COUNTRY_ID' => (string) $id, 'PORT_ID' => $port, 'value' => $v, 'ENG_DESC' => $name.' '];
        $yearDoc = fn (string $mult) => ['DataObject' => [[
            'export_by_countries' => json_encode([$country(156, '1', '5000'.$mult, 'China'), $country(156, '2', '2000'.$mult, 'China'), $country(328, '1', '0', 'Guyana'), $country(4999, '1', '7', 'Unmapped')]),
            'import_by_countries' => json_encode([$country(784, '1', '123', 'United Arab Emirates')]),
            'reexport_by_countries' => '[]',
        ]]];
        Http::fake([
            self::UN_PARTNERS => Http::response(['results' => []]),
            self::UN => Http::response(['results' => array_merge(
                [['reporterCode' => 156, 'reporterCodeIsoAlpha3' => 'CHN', 'isGroup' => false], ['reporterCode' => 328, 'reporterCodeIsoAlpha3' => 'GUY', 'isGroup' => false], ['reporterCode' => 784, 'reporterCodeIsoAlpha3' => 'ARE', 'isGroup' => false], ['reporterCode' => 1, 'reporterCodeIsoAlpha3' => 'WLD', 'isGroup' => true]],
                array_map(fn ($i) => ['reporterCode' => 2000 + $i, 'reporterCodeIsoAlpha3' => 'X'.str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'isGroup' => false], range(1, 200)),
            )]),
            self::API.'/GetStatistics' => Http::response($yearDoc('')),
        ]);
        $c = new ManafethPartnersConnector;

        $items = iterator_to_array($c->parse($c->fetch()), false);
        $by = collect($items)->filter(fn ($i) => $i instanceof ParsedRecord && $i->period === '2025' && ! str_ends_with($i->indicatorKey, '_YTD'))->mapWithKeys(fn ($r) => ["{$r->indicatorKey}|{$r->entityCode}" => $r->value])->all();

        $this->assertSame(7000.0, $by['manafeth.EXPORTS_TO|CHN'], 'منفذان لنفس الدولة يُجمعان');
        $this->assertArrayNotHasKey('manafeth.EXPORTS_TO|GUY', $by, 'الصفر يُتخطى');
        $this->assertCount(2, $by, 'رمز غير معروف في مرجع الأمم المتحدة يُتخطى');
        $this->assertSame(123.0, $by['manafeth.IMPORTS_FROM|ARE']);
        $china = collect($items)->first(fn ($i) => $i instanceof ParsedEntity && $i->code === 'CHN');
        $this->assertSame('China', $china->nameEn, 'الأسماء تُقصّ من المسافات الزائدة');

        $years = collect(Http::recorded())->map(fn ($p) => $p[0])->filter(fn (Request $r) => str_ends_with($r->url(), '/GetStatistics'))->map(fn (Request $r) => $r->data()['obj_json'])->values()->all();
        $this->assertSame(['2022', '2023', '2024', '2025', '2026'], $years, 'أربع سنوات كاملة ثم السنة الجارية للتراكمي');
    }

    public function test_partners_fail_loudly_if_the_un_reference_is_incomplete(): void
    {
        $this->freshHttp();
        $this->fakeStartYear(2026, 7);
        Http::fake([self::UN => Http::response(['results' => []]), self::UN_PARTNERS => Http::response(['results' => []])]);

        $this->expectExceptionMessage('مرجع رموز الدول');
        (new ManafethPartnersConnector)->fetch();
    }

    public function test_current_partial_year_is_published_as_year_to_date_indicators_never_as_a_full_year(): void
    {
        $this->freshHttp();
        $this->fakeStartYear(2026, 7);
        $row = fn (string $v) => ['DataObject' => [[
            'export_by_countries' => json_encode([['COUNTRY_ID' => '156', 'PORT_ID' => '1', 'value' => $v, 'ENG_DESC' => 'China']]),
            'import_by_countries' => '[]', 'reexport_by_countries' => '[]',
        ]]];
        Http::fake([
            self::UN_PARTNERS => Http::response(['results' => []]),
            self::UN => Http::response(['results' => array_map(fn ($i) => ['reporterCode' => $i === 0 ? 156 : 3000 + $i, 'reporterCodeIsoAlpha3' => $i === 0 ? 'CHN' : 'Z'.$i, 'reporterCodeIsoAlpha2' => $i === 0 ? 'CN' : 'Z'.$i, 'isGroup' => false], range(0, 200))]),
            self::API.'/GetStatistics' => function (Request $r) use ($row) {
                return Http::response($row($r->data()['obj_json'] === '2026' ? '4000' : '9000'));
            },
        ]);
        $c = new ManafethPartnersConnector;

        $items = iterator_to_array($c->parse($c->fetch()), false);
        $by = collect($items)->filter(fn ($i) => $i instanceof ParsedRecord)->mapWithKeys(fn ($r) => ["{$r->indicatorKey}|{$r->period}" => $r->value])->all();

        $this->assertSame(9000.0, $by['manafeth.EXPORTS_TO|2025']);
        $this->assertSame(4000.0, $by['manafeth.EXPORTS_TO_YTD|2026']);
        $this->assertArrayNotHasKey('manafeth.EXPORTS_TO|2026', $by);
        $china = collect($items)->first(fn ($i) => $i instanceof ParsedEntity && $i->code === 'CHN');
        $this->assertSame('الصين', $china->nameAr, 'الاسم العربي من بيانات ICU');
    }

    public function test_in_december_there_is_no_partial_year_so_no_ytd_request(): void
    {
        $this->freshHttp();
        $this->fakeStartYear(2025, 12);
        Http::fake([
            self::UN_PARTNERS => Http::response(['results' => []]),
            self::UN => Http::response(['results' => array_map(fn ($i) => ['reporterCode' => 3000 + $i, 'reporterCodeIsoAlpha3' => 'Z'.$i, 'isGroup' => false], range(1, 200))]),
            self::API.'/GetStatistics' => Http::response(['DataObject' => [['export_by_countries' => '[]', 'import_by_countries' => '[]', 'reexport_by_countries' => '[]']]]),
        ]);

        (new ManafethPartnersConnector)->fetch();

        $years = collect(Http::recorded())->map(fn ($p) => $p[0])->filter(fn (Request $r) => str_ends_with($r->url(), '/GetStatistics'))->map(fn (Request $r) => $r->data()['obj_json'])->values()->all();
        $this->assertSame(['2022', '2023', '2024', '2025'], $years);
    }

    /** @return array<string, mixed> */
    private function partnersFakes(string $exportRows, string $officialExports): array
    {
        return [
            self::UN_PARTNERS => Http::response(['results' => []]),
            self::UN => Http::response(['results' => array_merge(
                [['reporterCode' => 156, 'reporterCodeIsoAlpha3' => 'CHN', 'isGroup' => false]],
                array_map(fn ($i) => ['reporterCode' => 3000 + $i, 'reporterCodeIsoAlpha3' => 'X'.str_pad((string) $i, 3, '0', STR_PAD_LEFT), 'isGroup' => false], range(1, 200)),
            )]),
            self::API.'/GetStatistics' => Http::response(['DataObject' => [[
                'export_by_countries' => $exportRows, 'import_by_countries' => '[]', 'reexport_by_countries' => '[]',
                'export_value' => $officialExports, 'import_value' => '0', 'reexport_value' => '0',
            ]]]),
        ];
    }

    public function test_the_part_of_official_exports_not_distributed_by_country_is_shown_as_unallocated(): void
    {
        $this->freshHttp();
        $this->fakeStartYear(2025, 12);
        Http::fake($this->partnersFakes(json_encode([['COUNTRY_ID' => '156', 'PORT_ID' => '1', 'value' => '700', 'ENG_DESC' => 'China']]), '1000'));
        $c = new ManafethPartnersConnector;

        $items = iterator_to_array($c->parse($c->fetch()), false);
        $by = collect($items)->filter(fn ($i) => $i instanceof ParsedRecord && $i->period === '2025')->mapWithKeys(fn ($r) => ["{$r->indicatorKey}|{$r->entityCode}" => $r->value])->all();

        $this->assertSame(700.0, $by['manafeth.EXPORTS_TO|CHN']);
        $this->assertSame(300.0, $by['manafeth.EXPORTS_TO|ZZZ'], 'الفرق عن الإجمالي الرسمي يظهر «غير موزّع» فتتطابق المجاميع');
        $this->assertSame(1000.0, $by['manafeth.EXPORTS_TO|CHN'] + $by['manafeth.EXPORTS_TO|ZZZ']);
        $zzz = collect($items)->first(fn ($i) => $i instanceof ParsedEntity && $i->code === 'ZZZ');
        $this->assertSame('other', $zzz->type);
    }

    public function test_country_sums_exceeding_the_official_total_stop_the_run(): void
    {
        $this->freshHttp();
        $this->fakeStartYear(2025, 12);
        Http::fake($this->partnersFakes(json_encode([['COUNTRY_ID' => '156', 'PORT_ID' => '1', 'value' => '1200', 'ENG_DESC' => 'China']]), '1000'));

        $this->expectExceptionMessage('يتجاوز الإجمالي الرسمي');
        (new ManafethPartnersConnector)->fetch();
    }

    public function test_countries_without_a_code_stop_the_run_once_they_carry_real_weight(): void
    {
        $this->freshHttp();
        $this->fakeStartYear(2025, 12);
        Http::fake($this->partnersFakes(json_encode([
            ['COUNTRY_ID' => '156', 'PORT_ID' => '1', 'value' => '900', 'ENG_DESC' => 'China'],
            ['COUNTRY_ID' => '4321', 'PORT_ID' => '1', 'value' => '100', 'ENG_DESC' => 'Atlantis'],
        ]), '1000'));

        $this->expectExceptionMessage('Atlantis');
        (new ManafethPartnersConnector)->fetch();
    }

    public function test_corrected_manafeth_codes_resolve_to_the_right_country(): void
    {
        $this->freshHttp();
        $this->fakeStartYear(2025, 12);
        Http::fake($this->partnersFakes(json_encode([
            ['COUNTRY_ID' => '840', 'PORT_ID' => '1', 'value' => '300', 'ENG_DESC' => 'United States'],
            ['COUNTRY_ID' => '250', 'PORT_ID' => '1', 'value' => '200', 'ENG_DESC' => 'France'],
            ['COUNTRY_ID' => '200', 'PORT_ID' => '1', 'value' => '100', 'ENG_DESC' => 'Czech Republic'],
            ['COUNTRY_ID' => '0', 'PORT_ID' => '1', 'value' => '50', 'ENG_DESC' => 'Not Stated'],
        ]), '650'));
        $c = new ManafethPartnersConnector;

        $codes = collect(iterator_to_array($c->parse($c->fetch()), false))->filter(fn ($i) => $i instanceof ParsedRecord && $i->indicatorKey === 'manafeth.EXPORTS_TO')->pluck('entityCode')->unique()->values()->all();

        $this->assertEqualsCanonicalizing(['USA', 'FRA', 'CZE', 'ZZZ'], $codes, 'أمريكا وفرنسا لا تُتخطّيان، وتشيكوسلوفاكيا تصير التشيك، والرمز 0 غير محدد');
    }
}
