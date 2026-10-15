<?php

namespace Tests\Feature;

use App\Core\Ingestion\Data\ParsedAttribute;
use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\IngestRunner;
use App\Core\Ingestion\Promoter;
use App\Models\Dataset;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\DatabaseTestCase;
use Tests\Support\FakeConnector;

class CompanyApiTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['omanilab.connectors' => [FakeConnector::class]]);
        FakeConnector::reset();
        $this->artisan('datasets:sync')->assertSuccessful();
        // اسم المجموعة الذي يقرؤه المتحكم للحداثة
        DB::table('ops.datasets')->where('key', 'fake.test')->update(['key' => 'gleif.oman']);
        FakeConnector::$key = 'gleif.oman';

        FakeConnector::$custom = function () {
            $rows = [
                ['AAA', 'أوكيو للغاز', 'OQ Gas', 'ACTIVE', 'GENERAL', 'مسقط', '2019-05-01'],
                ['BBB', 'الأنوار للبلاط', 'Al Anwar Tiles', 'ACTIVE', 'GENERAL', 'ظفار', '2004-01-10'],
                ['CCC', null, 'Old Fund', 'INACTIVE', 'FUND', 'مسقط', '2011-09-09'],
            ];
            foreach ($rows as [$code, $ar, $en, $status, $cat, $gov, $date]) {
                yield new ParsedEntity("LEI{$code}0000000000000", $en, 'company', $ar);
                foreach (['status' => $status, 'category' => $cat, 'governorate' => $gov, 'creation_date' => $date, 'city' => $gov] as $k => $v) {
                    yield new ParsedAttribute("LEI{$code}0000000000000", $k, $v);
                }
            }
            yield new ParsedAttribute('LEIAAA0000000000000', 'other_names', ['OQ Gas Networks', 'Oman Gas']);
        };
        $run = app(IngestRunner::class)->run(Dataset::where('key', 'gleif.oman')->firstOrFail(), 'test');
        $this->assertSame('promoted', app(Promoter::class)->promote($run)->status);
    }

    public function test_descriptive_only_datasets_are_promoted_with_their_attributes(): void
    {
        $this->assertSame(3, DB::table('core.entities')->where('type', 'company')->count());
        $this->assertSame(16, DB::table('core.entity_attributes')->count());
        $this->assertSame(['OQ Gas Networks', 'Oman Gas'], json_decode(DB::table('core.entity_attributes')->where('key', 'other_names')->value('value'), true));
    }

    public function test_attributes_update_only_when_their_value_changes(): void
    {
        $before = DB::table('core.entity_attributes')->where('key', 'status')->orderBy('entity_id')->pluck('updated_at', 'entity_id')->all();

        $this->travel(2)->hours();
        FakeConnector::$token = 'v2';
        $custom = FakeConnector::$custom;
        FakeConnector::$custom = function () use ($custom) {
            foreach ($custom() as $item) {
                // الشركة CCC صارت نشطة؛ الباقي كما هو
                yield $item instanceof ParsedAttribute && $item->entityCode === 'LEICCC0000000000000' && $item->key === 'status' ? new ParsedAttribute($item->entityCode, 'status', 'ACTIVE') : $item;
            }
        };
        $run = app(IngestRunner::class)->run(Dataset::where('key', 'gleif.oman')->firstOrFail(), 'test', true);
        $this->assertSame('promoted', app(Promoter::class)->promote($run)->status);
        $this->assertSame(1, $run->refresh()->promoted_count, 'سمة واحدة تغيّرت فقط');

        $after = DB::table('core.entity_attributes')->where('key', 'status')->orderBy('entity_id')->pluck('updated_at', 'entity_id')->all();
        $changed = array_keys(array_filter($after, fn ($v, $k) => $v !== $before[$k], ARRAY_FILTER_USE_BOTH));
        $this->assertCount(1, $changed);
    }

    public function test_list_searches_arabic_and_english_names_and_paginates(): void
    {
        $this->getJson('/api/v1/companies?q=أوكيو')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.name_en', 'OQ Gas');
        $this->getJson('/api/v1/companies?q=anwar')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.governorate', 'ظفار');
        $this->getJson('/api/v1/companies?per_page=2&page=2')->assertOk()->assertJsonPath('total', 3)->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/companies?q=zzzz')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_list_filters_by_attributes_and_sorts_by_creation(): void
    {
        $this->getJson('/api/v1/companies?status=ACTIVE')->assertJsonPath('total', 2);
        $this->getJson('/api/v1/companies?governorate=مسقط&category=FUND')->assertJsonPath('total', 1)->assertJsonPath('data.0.name_en', 'Old Fund');

        $names = array_column($this->getJson('/api/v1/companies?sort=created')->json('data'), 'name_en');
        $this->assertSame(['OQ Gas', 'Old Fund', 'Al Anwar Tiles'], $names, 'الأحدث تأسيسًا أولًا');
    }

    public function test_short_queries_never_match_lei_codes_but_long_ones_do(): void
    {
        $this->getJson('/api/v1/companies?q=lei')->assertJsonPath('total', 0);
        $this->getJson('/api/v1/companies?q=LEIAAA000')->assertJsonPath('total', 1);
    }

    public function test_stats_group_by_status_governorate_and_creation_year(): void
    {
        $r = $this->getJson('/api/v1/companies/stats')->assertOk()->json();

        $this->assertSame(3, $r['total']);
        $this->assertSame(['ACTIVE' => 2, 'INACTIVE' => 1], array_column($r['by_status'], 'count', 'label'));
        $this->assertSame(['مسقط' => 2, 'ظفار' => 1], array_column($r['by_governorate'], 'count', 'label'));
        $this->assertSame(['2004' => 1, '2011' => 1, '2019' => 1], array_column($r['by_year'], 'count', 'year'));
    }

    public function test_company_profile_returns_all_attributes_and_source_freshness(): void
    {
        $this->getJson('/api/v1/companies/LEIAAA0000000000000')->assertOk()
            ->assertJsonPath('name_ar', 'أوكيو للغاز')
            ->assertJsonPath('attributes.status', 'ACTIVE')
            ->assertJsonPath('attributes.other_names.1', 'Oman Gas')
            ->assertJsonPath('dataset.key', 'gleif.oman');

        $this->getJson('/api/v1/companies/NOPE')->assertNotFound();
        $this->getJson('/api/v1/companies?sort=bogus')->assertStatus(422);
    }

    public function test_countries_are_not_listed_as_companies(): void
    {
        DB::table('core.entities')->insert(['code' => 'OMN', 'type' => 'country', 'name_en' => 'Oman']);

        $this->getJson('/api/v1/companies/OMN')->assertNotFound();
        $this->getJson('/api/v1/companies')->assertJsonPath('total', 3);
    }
}
