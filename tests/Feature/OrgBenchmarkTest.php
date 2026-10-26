<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Prices\DbCurrencyConverter;
use App\Modules\ValueChain\Units;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\DatabaseTestCase;

class OrgBenchmarkTest extends DatabaseTestCase
{
    private int $chain;

    protected function setUp(): void
    {
        parent::setUp();
        DB::unprepared('TRUNCATE intel.value_chains, intel.price_series, app.organizations, app.supplier_prices RESTART IDENTITY CASCADE');
        // سلسلة مصغّرة: مدخل (وزن 0.6) ← منتج، بسلاسل شهرية معروفة
        $src = DB::table('core.sources')->insertGetId(['key' => 't', 'name_ar' => 't', 'name_en' => 't']);
        $ds = DB::table('ops.datasets')->insertGetId(['key' => 't.ds', 'source_id' => $src, 'name_ar' => 't', 'name_en' => 't', 'section' => 't', 'connector' => 'x', 'interval_minutes' => 60, 'status' => 'fresh']);
        $omn = DB::table('core.entities')->insertGetId(['code' => 'OMN', 'name_en' => 'Oman', 'name_ar' => 'عُمان']);
        $wld = DB::table('core.entities')->insertGetId(['code' => 'USA', 'name_en' => 'US', 'name_ar' => 'أمريكا']);
        $ind = fn (string $k, string $u) => DB::table('core.indicators')->insertGetId(['key' => $k, 'dataset_id' => $ds, 'name_ar' => $k, 'name_en' => $k, 'unit' => $u, 'frequency' => 'M']);
        $inp = $ind('t.input', 'مؤشر');
        $cust = $ind('t.cust', 'دولار/طن');
        foreach (range(0, 29) as $i) {
            $p = (new \DateTimeImmutable('2024-01-01'))->modify("+{$i} month")->format('Y-m');
            DB::table('core.observations')->insert(['indicator_id' => $inp, 'entity_id' => $wld, 'period' => $p, 'value' => $p < '2025-06' ? 100 : 120]);
            DB::table('core.observations')->insert(['indicator_id' => $cust, 'entity_id' => $omn, 'period' => $p, 'value' => 500]);
        }
        $this->chain = DB::table('intel.value_chains')->insertGetId(['slug' => 'mini', 'name_ar' => 'مصغرة', 'name_en' => 'mini', 'created_at' => now(), 'updated_at' => now()]);
        $in = DB::table('intel.chain_nodes')->insertGetId(['chain_id' => $this->chain, 'slug' => 'in', 'name_ar' => 'مدخل', 'name_en' => 'in', 'kind' => 'raw', 'stage' => 0, 'unit' => 'طن']);
        $out = DB::table('intel.chain_nodes')->insertGetId(['chain_id' => $this->chain, 'slug' => 'out', 'name_ar' => 'منتج', 'name_en' => 'out', 'kind' => 'final', 'stage' => 1, 'unit' => 'طن']);
        DB::table('intel.chain_edges')->insert(['chain_id' => $this->chain, 'from_node_id' => $in, 'to_node_id' => $out, 'weight' => 0.6, 'weight_min' => 0.5, 'weight_max' => 0.7]);
        $s1 = DB::table('intel.price_series')->insertGetId(['indicator_key' => 't.input', 'entity_code' => 'USA', 'label_ar' => 'مؤشر المدخل', 'price_type' => 'index', 'confidence' => 'high', 'frequency' => 'M']);
        $s2 = DB::table('intel.price_series')->insertGetId(['indicator_key' => 't.cust', 'entity_code' => 'OMN', 'label_ar' => 'قيمة جمركية', 'price_type' => 'customs_unit_value', 'confidence' => 'medium', 'frequency' => 'M', 'unit' => 'دولار/طن', 'incoterm' => 'CIF', 'market' => 'عُمان']);
        DB::table('intel.node_series')->insert([['node_id' => $in, 'series_id' => $s1, 'role' => 'primary', 'priority' => 1], ['node_id' => $out, 'series_id' => $s2, 'role' => 'primary', 'priority' => 1]]);
    }

    private function member(string $role, ?int $org = null): User
    {
        $u = User::create(['name' => 'م', 'email' => uniqid('o').'@example.com', 'password' => 'correct-horse-battery']);
        $org ??= DB::table('app.organizations')->insertGetId(['name_ar' => 'جهة', 'kind' => 'government', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('app.organization_members')->insert(['organization_id' => $org, 'user_id' => $u->id, 'role' => $role, 'created_at' => now()]);

        return $u;
    }

    private function outNode(): int
    {
        return (int) DB::table('intel.chain_nodes')->where('slug', 'out')->value('id');
    }

    public function test_units_and_offline_fx(): void
    {
        $this->assertSame(1000.0, Units::factor('طن', 'كغم'));
        $this->assertSame(0.001, Units::factor('كغم', 'طن'));
        $this->assertNull(Units::factor('طن', 'م³'));
        $this->assertSame(['USD', 'طن'], Units::parsePriceUnit('دولار/طن'));
        $this->assertEqualsWithDelta(0.3845 * 500, (new DbCurrencyConverter)->toOmr(500, 'USD'), 0.001);
        $this->assertNull((new DbCurrencyConverter)->toOmr(1, 'XYZ'));
    }

    public function test_prices_are_isolated_per_organization(): void
    {
        $a = $this->member('purchaser');
        $b = $this->member('purchaser');
        $this->actingAs($a)->postJson('/api/v1/intel/org/prices', ['product_label' => 'حديد 12 مم', 'supplier_name' => 'مورد أ', 'price' => 230, 'currency' => 'OMR', 'unit' => 'طن', 'price_date' => '2026-09-01', 'node_id' => $this->outNode()])->assertCreated();
        $this->actingAs($b)->getJson('/api/v1/intel/org/prices')->assertJsonCount(0, 'data');
        $id = DB::table('app.supplier_prices')->value('id');
        $this->actingAs($b)->getJson("/api/v1/intel/org/benchmark?price_id={$id}")->assertNotFound();
        $this->actingAs($b)->deleteJson("/api/v1/intel/org/prices/{$id}")->assertNotFound();
        $this->actingAs(User::create(['name' => 'x', 'email' => uniqid('n').'@example.com', 'password' => 'correct-horse-battery']))->getJson('/api/v1/intel/org/prices')->assertForbidden();
    }

    public function test_benchmark_reference_range_should_cost_and_savings(): void
    {
        $u = $this->member('analyst');
        // تعاقد في 2025-01 (المدخل 100) بسعر 250 ر.ع/طن لكمية 100 طن؛ المدخل الآن 120 ⇒ معامل 0.6×1.2 + 0.4 = 1.12
        $this->actingAs($u)->postJson('/api/v1/intel/org/prices', ['product_label' => 'منتج', 'supplier_name' => 'مورد', 'price' => 250, 'currency' => 'OMR', 'unit' => 'طن', 'quantity' => 100, 'price_date' => '2025-01-15', 'node_id' => $this->outNode()])->assertCreated();
        $id = DB::table('app.supplier_prices')->value('id');
        $r = $this->actingAs($u)->getJson("/api/v1/intel/org/benchmark?price_id={$id}")->assertOk()->json();

        $this->assertEqualsWithDelta(1.12, $r['should_cost']['factor'], 0.0001);
        $this->assertEqualsWithDelta(280.0, $r['should_cost']['should_cost_omr'], 0.01);
        // المرجع: 500 دولار CIF ⇒ 192.25 ر.ع + رسم 5% = 201.86
        $this->assertEqualsWithDelta(201.86, $r['range']['median'], 0.01);
        $this->assertSame('أعلى من نطاق السوق', $r['position']['label']);
        $this->assertEqualsWithDelta((250 - 201.8625) * 100, $r['savings_vs_median_omr'], 0.1);
        $this->assertSame(1, DB::table('intel.benchmark_results')->count(), 'لقطة محفوظة');
        $this->actingAs($u)->getJson("/api/v1/intel/org/benchmark?price_id={$id}")->assertOk();
        $this->assertSame(1, DB::table('intel.benchmark_results')->count(), 'نفس النتيجة لا تُكرَّر');
    }

    public function test_upload_supplier_prices_csv(): void
    {
        $u = $this->member('purchaser');
        $f = UploadedFile::fake()->createWithContent('p.csv', "التاريخ,المنتج,المورد,السعر,العملة,الوحدة,الكمية\n2026-08-01,حديد 16 مم,مورد ب,240,OMR,طن,50\nسيء,x,y,1,OMR,طن,1\n");
        $this->actingAs($u)->post('/api/v1/intel/org/prices/upload', ['file' => $f, 'node_id' => $this->outNode()], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('inserted', 1)->assertJsonCount(1, 'warnings');
        $this->assertEqualsWithDelta(240, (float) DB::table('app.supplier_prices')->value('price_omr_per_unit'), 0.001);
    }
}
