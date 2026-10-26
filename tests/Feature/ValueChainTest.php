<?php

namespace Tests\Feature;

use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\RawPayload;
use App\Models\User;
use App\Modules\ValueChain\Connectors\BlsPpiChainConnector;
use App\Modules\ValueChain\Connectors\ComtradeChainUnitValuesConnector;
use App\Modules\ValueChain\Connectors\ShfeFuturesConnector;
use App\Modules\ValueChain\WeightValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\DatabaseTestCase;

class ValueChainTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::unprepared('TRUNCATE intel.value_chains, intel.price_series, app.organizations, app.supplier_prices RESTART IDENTITY CASCADE');
    }

    private function user(bool $admin = false): User
    {
        return User::create(['name' => 'م', 'email' => uniqid('v').'@example.com', 'password' => 'correct-horse-battery', 'is_admin' => $admin]);
    }

    public function test_seed_loads_three_documented_chains_with_sources_and_revision(): void
    {
        $this->artisan('chains:seed')->assertSuccessful();

        $this->assertSame(['copper-power-cable', 'ready-mix-concrete', 'rebar'], DB::table('intel.value_chains')->orderBy('slug')->pluck('slug')->all());
        $rebar = DB::table('intel.value_chains')->where('slug', 'rebar')->first();
        $this->assertSame(1, (int) $rebar->version);
        $this->assertSame(1, DB::table('intel.chain_revisions')->where('chain_id', $rebar->id)->count());
        // كل وزن غير مفقود له مصدر واحد على الأقل ونطاق يحتويه
        $edges = DB::table('intel.chain_edges')->whereNotNull('weight')->get();
        $this->assertNotEmpty($edges);
        foreach ($edges as $e) {
            $this->assertTrue(DB::table('intel.edge_sources')->where('edge_id', $e->id)->exists(), "حافة {$e->id} بلا مصدر");
            $this->assertTrue((float) $e->weight_min <= (float) $e->weight && (float) $e->weight <= (float) $e->weight_max);
        }
        // أوزان المدخلات المكتملة لكل هدف لا تتجاوز 1.05
        foreach (DB::table('intel.chain_edges')->whereNotNull('weight')->selectRaw('to_node_id, sum(weight) s')->groupBy('to_node_id')->get() as $t) {
            $this->assertLessThanOrEqual(1.05, (float) $t->s);
        }
        $this->artisan('chains:seed')->expectsOutputToContain('تخطّي')->assertSuccessful();
    }

    public function test_editing_requires_role_validates_range_and_records_revision(): void
    {
        $this->artisan('chains:seed', ['slug' => 'copper-power-cable'])->assertSuccessful();
        $edge = DB::table('intel.chain_edges')->whereNotNull('weight')->orderByDesc('weight')->first();
        $url = "/api/v1/intel/chains/copper-power-cable/edges/{$edge->id}";

        $this->getJson('/api/v1/intel/chains')->assertUnauthorized();
        $this->actingAs($this->user())->patchJson($url, ['weight' => 0.6])->assertForbidden();

        $admin = $this->user(true);
        $this->actingAs($admin)->patchJson($url, ['weight' => 0.9])->assertStatus(422);
        $this->actingAs($admin)->patchJson($url, ['weight' => 0.6, 'weight_min' => 0.55, 'weight_max' => 0.7, 'change_note' => 'مراجعة المحلل'])->assertOk()->assertJsonPath('chain.version', 2);
        $this->assertSame('مراجعة المحلل', DB::table('intel.chain_revisions')->orderByDesc('id')->value('note'));
        $snapshot = json_decode((string) DB::table('intel.chain_revisions')->orderByDesc('id')->value('snapshot'), true);
        $this->assertEquals(0.6, (float) collect($snapshot['edges'])->firstWhere('id', $edge->id)['weight']);
    }

    public function test_analyst_member_can_edit_and_attach_requires_existing_series(): void
    {
        $this->artisan('chains:seed', ['slug' => 'rebar'])->assertSuccessful();
        $analyst = $this->user();
        $org = DB::table('app.organizations')->insertGetId(['name_ar' => 'جهة اختبار', 'kind' => 'government', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('app.organization_members')->insert(['organization_id' => $org, 'user_id' => $analyst->id, 'role' => 'analyst', 'created_at' => now()]);
        $node = DB::table('intel.chain_nodes')->where('slug', 'billet')->value('id');

        $this->actingAs($analyst)->getJson('/api/v1/auth/me')->assertJsonPath('user.can_edit_chains', true);
        $this->actingAs($analyst)->postJson("/api/v1/intel/chains/rebar/nodes/{$node}/series", [
            'indicator_key' => 'no.such', 'entity_code' => 'OMN', 'label_ar' => 'x', 'price_type' => 'index', 'confidence' => 'low', 'role' => 'proxy',
        ])->assertStatus(422);
        $this->actingAs($analyst)->patchJson("/api/v1/intel/chains/rebar/nodes/{$node}", ['notes' => 'ملاحظة محلل'])->assertOk();
    }

    public function test_admin_creates_new_chain_with_unique_slug(): void
    {
        $admin = $this->user(true);
        $body = ['slug' => 'aluminium-cable', 'name_ar' => 'كابلات الألمنيوم', 'name_en' => 'Aluminium cable'];
        $this->actingAs($admin)->postJson('/api/v1/intel/chains', $body)->assertCreated()->assertJsonPath('chain.status', 'draft');
        $this->actingAs($admin)->postJson('/api/v1/intel/chains', $body)->assertStatus(422);
        $this->actingAs($admin)->postJson('/api/v1/intel/chains/aluminium-cable/nodes', ['slug' => 'al', 'name_ar' => 'الألمنيوم', 'name_en' => 'Aluminium', 'kind' => 'raw', 'stage' => 0, 'hs_codes' => ['7601']])->assertOk();
    }

    public function test_supplier_price_index_is_k_anonymous(): void
    {
        $this->artisan('chains:seed', ['slug' => 'rebar'])->assertSuccessful();
        $node = DB::table('intel.chain_nodes')->where('slug', 'rebar')->value('id');
        $orgs = [];
        foreach (range(1, 3) as $i) {
            $orgs[] = DB::table('app.organizations')->insertGetId(['name_ar' => "جهة {$i}", 'kind' => 'government', 'created_at' => now(), 'updated_at' => now()]);
        }
        $add = fn (int $org, float $p) => DB::table('app.supplier_prices')->insert(['organization_id' => $org, 'node_id' => $node, 'product_label' => 'حديد 12مم', 'supplier_name' => 'مورد', 'price' => $p, 'currency' => 'OMR', 'unit' => 'طن', 'price_date' => '2026-09-10', 'price_omr_per_unit' => $p, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([[$orgs[0], 230], [$orgs[0], 232], [$orgs[1], 240], [$orgs[1], 238]] as [$o, $p]) {
            $add($o, $p);
        }
        $this->assertSame(0, DB::table('intel.supplier_price_index')->count(), 'جهتان فقط: لا يُنشر شيء');
        $add($orgs[2], 250);
        $row = DB::table('intel.supplier_price_index')->first();
        $this->assertSame(3, (int) $row->n_orgs);
        $this->assertEqualsWithDelta(238, (float) $row->median_omr, 0.01);
    }

    public function test_weight_validator_recovers_known_elasticity_and_flags_conflict(): void
    {
        mt_srand(7);
        $x = [];
        $y = [];
        $px = 100.0;
        $py = 100.0;
        $d = new \DateTimeImmutable('2018-01-01');
        $prev = null;
        for ($i = 0; $i < 80; $i++) {
            $m = $d->modify("+{$i} month")->format('Y-m');
            $shock = (mt_rand() / mt_getrandmax() - 0.5) * 0.1;
            $px *= exp($shock);
            $x[$m] = $px;
            // المنتج يتبع المدخل بعد شهرين بمرونة 0.6
            $py *= exp(0.6 * ($prev2 ?? 0.0) + (mt_rand() / mt_getrandmax() - 0.5) * 0.004);
            $y[$m] = $py;
            $prev2 = $prev;
            $prev = $shock;
        }
        $v = new WeightValidator;
        $ok = $v->regress(['key' => 'y', 'points' => $y], [['edge' => (object) ['weight' => 0.62, 'weight_min' => 0.58, 'weight_max' => 0.75], 'node' => (object) ['slug' => 'copper', 'name_ar' => 'نحاس'], 'series' => ['key' => 'x', 'points' => $x]]]);
        $this->assertSame(2, $ok['inputs'][0]['lag_months']);
        $this->assertEqualsWithDelta(0.6, $ok['inputs'][0]['beta'], 0.05);
        $this->assertFalse($ok['flagged']);

        $bad = $v->regress(['key' => 'y', 'points' => $y], [['edge' => (object) ['weight' => 0.1, 'weight_min' => 0.05, 'weight_max' => 0.15], 'node' => (object) ['slug' => 'copper', 'name_ar' => 'نحاس'], 'series' => ['key' => 'x', 'points' => $x]]]);
        $this->assertTrue($bad['flagged'], 'وزن 0.1 بعيد عن مرونة 0.6 المقدّرة');
    }

    public function test_comtrade_unit_values_filter_small_lots_and_outliers(): void
    {
        $c = new ComtradeChainUnitValuesConnector;
        $rows = [];
        foreach (range(1, 12) as $m) {
            $rows[] = ['M', '721420', 'OMN', sprintf('2025-%02d', $m), 500_000.0, 1_000_000.0, false]; // 500 دولار/طن
        }
        $rows[] = ['M', '721420', 'OMN', '2024-12', 9_000.0, 2_000.0, false];           // 2 طن فقط: يُستبعد
        $rows[] = ['M', '721420', 'OMN', '2024-11', 4_000_000.0, 1_000_000.0, false];   // 4000 دولار/طن: شاذ
        $rows[] = ['M', '721420', 'OMN', '2024-10', 520_000.0, 1_000_000.0, true];      // تقديري: يبقى ويُوسَم
        $out = $c->clean($rows);
        $periods = array_column($out, 3);

        $this->assertNotContains('2024-12', $periods);
        $this->assertNotContains('2024-11', $periods);
        $this->assertContains('2024-10', $periods);
        $this->assertEqualsWithDelta(500.0, $out[0][4], 0.01);
        // سوق آخر بسلسلة متسقة داخليًا لكنها أعلى بكثير من بقية الأسواق (شحنات متخصصة): تُستبعد
        $mixed = $rows;
        foreach (range(1, 6) as $m) {
            $mixed[] = ['M', '721420', 'SAU', sprintf('2025-%02d', $m), 2_000_000.0, 1_000_000.0, false];
        }
        $this->assertNotContains('SAU', array_column($c->clean($mixed), 2));
        $records = iterator_to_array($c->parse(new RawPayload(json_encode($rows))), false);
        $keys = array_unique(array_map(fn ($r) => $r->indicatorKey ?? null, $records));
        $this->assertContains('ctm.est_m.721420', $keys);
    }

    public function test_comtrade_saves_partial_results_when_quota_runs_out(): void
    {
        $row = ['reporterCode' => 512, 'cmdCode' => '721420', 'primaryValue' => 500000, 'netWgt' => 1000000, 'customsCode' => 'C00', 'motCode' => 0, 'partner2Code' => 0, 'isNetWgtEstimated' => false];
        Http::fakeSequence('comtradeapi.un.org/*')->push(['data' => [$row]])->push('{ "statusCode": 403, "message": "Out of call volume quota." }', 403);
        $c = new ComtradeChainUnitValuesConnector;
        $payload = json_decode($c->fetch()->body, true);

        $this->assertCount(1, $payload, 'حُفظ الشهر الأول قبل نفاد الحصة');

        Http::fakeSequence('comtradeapi.un.org/*')->push('{ "statusCode": 403, "message": "Out of call volume quota." }', 403);
        $this->expectException(\RuntimeException::class);
        (new ComtradeChainUnitValuesConnector)->fetch();
    }

    public function test_bls_ppi_and_shfe_parsers(): void
    {
        Http::fake(['api.bls.gov/*' => Http::response(['status' => 'REQUEST_SUCCEEDED', 'Results' => ['series' => [['seriesID' => 'WPU1333', 'data' => [['year' => '2026', 'period' => 'M09', 'value' => '395.3'], ['year' => '2026', 'period' => 'M13', 'value' => '1']]]]]])]);
        $b = new BlsPpiChainConnector;
        $recs = array_values(array_filter(iterator_to_array($b->parse($b->fetch()), false), fn ($r) => $r instanceof ParsedRecord));
        $this->assertSame('bls.ppi.wpu1333', $recs[0]->indicatorKey);
        $this->assertSame('2026-09', $recs[0]->period);
        $this->assertSame(['2026-09'], array_values(array_unique(array_map(fn ($r) => $r->period, $recs))), 'M13 (المتوسط السنوي) يُستبعد');

        $s = new ShfeFuturesConnector;
        $settles = $s->mainSettles([
            ['PRODUCTGROUPID' => 'rb ', 'DELIVERYMONTH' => '2611', 'SETTLEMENTPRICE' => 3098, 'OPENINTEREST' => 1000],
            ['PRODUCTGROUPID' => 'rb', 'DELIVERYMONTH' => '2701', 'SETTLEMENTPRICE' => 3072, 'OPENINTEREST' => 500000],
            ['PRODUCTGROUPID' => 'rb', 'DELIVERYMONTH' => '小计', 'SETTLEMENTPRICE' => 1, 'OPENINTEREST' => 9e9],
        ]);
        $this->assertSame(['rb' => 3072.0], $settles, 'العقد الأعلى في المراكز المفتوحة، وتُستبعد صفوف المجاميع');
    }

    public function test_orgs_command_and_user_identity(): void
    {
        $this->artisan('users:create', ['email' => 'buyer@example.com', '--password' => 'correct-horse-battery'])->assertSuccessful();
        $this->assertTrue(DB::table('app.user_identities')->where('provider', 'password')->where('subject', 'buyer@example.com')->exists());
        $this->artisan('orgs:manage', ['action' => 'create', '--name' => 'وزارة اختبار'])->assertSuccessful();
        $org = (int) DB::table('app.organizations')->max('id');
        $this->artisan('orgs:manage', ['action' => 'add-member', '--org' => $org, '--email' => 'buyer@example.com', '--role' => 'purchaser'])->assertSuccessful();
        $u = User::where('email', 'buyer@example.com')->first();
        $this->assertSame('purchaser', $u->memberships()[0]['role']);
        $this->assertFalse($u->canEditChains());
    }
}
