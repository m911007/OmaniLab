<?php

namespace Tests\Feature;

use App\Core\Ingestion\Data\ParsedAttribute;
use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Modules\Companies\Connectors\GleifLeiCountsConnector;
use App\Modules\Companies\Connectors\GleifOmanConnector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\DatabaseTestCase;

class GleifConnectorTest extends DatabaseTestCase
{
    private const API = 'https://api.gleif.org/api/v1';

    protected function setUp(): void
    {
        parent::setUp();
        config(['omanilab.ingest.gleif_throttle_microseconds' => 0]);
    }

    /**
     * @param  array<string, mixed>  $over
     * @return array<string, mixed>
     */
    private function lei(string $lei, string $name, string $lang, array $over = []): array
    {
        return ['attributes' => ['lei' => $lei, 'entity' => array_replace_recursive([
            'legalName' => ['name' => $name, 'language' => $lang],
            'otherNames' => [['name' => 'ENGLISH ALT', 'language' => 'en']],
            'transliteratedOtherNames' => [],
            'legalAddress' => ['addressLines' => ['شارع مسقط', 'مبنى 5'], 'city' => 'مَسْقَط', 'region' => 'OM-MA', 'country' => 'OM', 'postalCode' => '111'],
            'registeredAt' => ['id' => 'RA000474'], 'registeredAs' => '1647071', 'jurisdiction' => 'OM', 'category' => 'GENERAL',
            'legalForm' => ['id' => 'HA2W'], 'status' => 'ACTIVE', 'creationDate' => '2015-03-15T00:00:00Z',
        ], $over), 'registration' => ['status' => 'ISSUED', 'lastUpdateDate' => '2026-09-27T07:33:46Z', 'nextRenewalDate' => '2027-09-27T07:33:48Z']]];
    }

    /** @param  list<array<string, mixed>>  $records */
    private function fakeOman(array $records): void
    {
        $this->freshHttp();
        Http::fake([self::API.'/lei-records*' => Http::response([
            'meta' => ['goldenCopy' => ['publishDate' => '2026-10-14T16:00:00Z'], 'pagination' => ['lastPage' => 1, 'total' => count($records)]],
            'data' => $records,
        ])]);
    }

    public function test_probe_uses_the_daily_golden_copy_date(): void
    {
        $this->fakeOman([]);

        $p = (new GleifOmanConnector)->probe();

        $this->assertSame('2026-10-14T16:00:00Z', $p->token);
        $this->assertSame('2026-10-14', $p->publishedAt->toDateString());
    }

    public function test_probe_fails_loudly_without_the_publish_date(): void
    {
        $this->freshHttp();
        Http::fake([self::API.'/*' => Http::response(['meta' => []])]);

        $this->expectException(RuntimeException::class);
        (new GleifOmanConnector)->probe();
    }

    public function test_parse_builds_company_entities_with_cleaned_attributes(): void
    {
        $this->fakeOman([
            $this->lei('LEI0000000000000001A', 'شركة الأنوار', 'ar'),
            $this->lei('LEI0000000000000002B', 'AL ANWAR LLC', 'en', ['otherNames' => [], 'legalAddress' => ['city' => 'MUSCAT', 'region' => 'OM-ZU'], 'creationDate' => '2015-11-01T00:00:00Z']),
        ]);
        $c = new GleifOmanConnector;

        $items = iterator_to_array($c->parse($c->fetch()), false);
        $entities = collect($items)->filter(fn ($i) => $i instanceof ParsedEntity)->keyBy('code');
        $attrs = collect($items)->filter(fn ($i) => $i instanceof ParsedAttribute)->groupBy('entityCode')->map(fn ($g) => $g->pluck('value', 'key')->all());

        $this->assertSame('company', $entities['LEI0000000000000001A']->type);
        $this->assertSame('شركة الأنوار', $entities['LEI0000000000000001A']->nameAr);
        $this->assertSame('ENGLISH ALT', $entities['LEI0000000000000001A']->nameEn, 'الاسم الإنجليزي البديل');
        $this->assertNull($entities['LEI0000000000000002B']->nameAr);
        $this->assertSame('AL ANWAR LLC', $entities['LEI0000000000000002B']->nameEn);

        $a = $attrs['LEI0000000000000001A'];
        $this->assertSame('مسقط', $a['city'], 'التشكيل أُزيل');
        $this->assertSame('مسقط', $a['governorate']);
        $this->assertSame('2015-03-15', $a['creation_date']);
        $this->assertSame('شارع مسقط، مبنى 5', $a['address']);
        $this->assertSame('Muscat', $attrs['LEI0000000000000002B']['city'], 'MUSCAT صارت Muscat');
        $this->assertSame('ظفار', $attrs['LEI0000000000000002B']['governorate']);
    }

    public function test_creation_year_counts_become_an_observation_series(): void
    {
        $this->fakeOman([
            $this->lei('LEI0000000000000001A', 'أ', 'ar'),
            $this->lei('LEI0000000000000002B', 'ب', 'ar'),
            $this->lei('LEI0000000000000003C', 'ج', 'ar', ['creationDate' => '2020-01-01T00:00:00Z']),
            $this->lei('LEI0000000000000004D', 'د', 'ar', ['creationDate' => null]),
        ]);
        $c = new GleifOmanConnector;

        $records = collect(iterator_to_array($c->parse($c->fetch()), false))->filter(fn ($i) => $i instanceof ParsedRecord)
            ->mapWithKeys(fn ($r) => [$r->period => $r->value])->all();

        $this->assertSame(['2015' => 2.0, '2020' => 1.0], $records, 'بلا تاريخ تأسيس لا تُحتسب');
    }

    public function test_lei_counts_map_iso2_to_iso3_through_the_world_bank_attribute(): void
    {
        $this->freshHttp();
        $ids = [];
        foreach (range(1, 160) as $i) {
            $code = sprintf('C%02d', $i);
            $ids[$code] = DB::table('core.entities')->insertGetId(['code' => $code, 'type' => 'country', 'name_en' => $code]);
            DB::table('core.entity_attributes')->insert(['entity_id' => $ids[$code], 'key' => 'iso2', 'value' => json_encode(sprintf('Z%02d', $i))]);
        }
        Http::fake([
            self::API.'/countries*' => Http::response(['data' => [['attributes' => ['code' => 'Z01']], ['attributes' => ['code' => 'Z02']], ['attributes' => ['code' => 'ZZ']]], 'meta' => ['pagination' => ['lastPage' => 1]]]),
            self::API.'/lei-records*' => function ($req) {
                $q = urldecode($req->url());
                $total = str_contains($q, '=Z01') ? 6093 : (str_contains($q, '=Z02') ? 264 : 0);

                return Http::response(['meta' => ['goldenCopy' => ['publishDate' => '2026-10-14T16:00:00Z'], 'pagination' => ['total' => $total]], 'data' => []]);
            },
        ]);
        $c = new GleifLeiCountsConnector;

        $records = collect(iterator_to_array($c->parse($c->fetch()), false))->filter(fn ($i) => $i instanceof ParsedRecord)
            ->mapWithKeys(fn ($r) => [$r->entityCode => [$r->period, $r->value]])->all();

        $this->assertSame(['C01' => ['2026-10', 6093.0], 'C02' => ['2026-10', 264.0]], $records, 'صفر أو غير مرتبط بدولة يُتخطى؛ الفترة شهر اللقطة');
    }

    public function test_lei_counts_wait_for_the_country_table_instead_of_publishing_nothing(): void
    {
        $this->freshHttp();
        Http::fake([
            self::API.'/countries*' => Http::response(['data' => [['attributes' => ['code' => 'OM']]], 'meta' => ['pagination' => ['lastPage' => 1]]]),
            self::API.'/lei-records*' => Http::response(['meta' => ['goldenCopy' => ['publishDate' => '2026-10-14T16:00:00Z'], 'pagination' => ['total' => 5]], 'data' => []]),
        ]);
        $c = new GleifLeiCountsConnector;
        $payload = $c->fetch();

        $this->expectExceptionMessage('ينتظر بيانات الدول');
        iterator_to_array($c->parse($payload));
    }
}
