<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\ValueChain\ChainAnalytics;
use App\Modules\ValueChain\SeriesReader;
use App\Modules\ValueChain\UploadParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\DatabaseTestCase;

class ChainExplorerTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::unprepared('TRUNCATE intel.value_chains, intel.price_series, intel.uploads, intel.uploaded_series, app.organizations RESTART IDENTITY CASCADE');
    }

    private function admin(): User
    {
        return User::create(['name' => 'م', 'email' => uniqid('e').'@example.com', 'password' => 'correct-horse-battery', 'is_admin' => true]);
    }

    public function test_upload_parser_normalises_periods_and_reports_bad_rows(): void
    {
        $p = new UploadParser;
        $this->assertSame('2025-07', $p->period('2025-07-15', 'M'));
        $this->assertSame('2025-07', $p->period('2025/7', 'M'));
        $this->assertSame('2025-Q3', $p->period('2025-08', 'Q'));
        $this->assertSame('2025', $p->period('2025-Q2', 'A'));
        $this->assertSame('2024-01', $p->period('45292', 'M'), 'تاريخ Excel الرقمي 45292 = 2024-01-01');
        $this->assertNull($p->period('2025-13', 'M'));

        $f = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($f, "\xEF\xBB\xBFالتاريخ,إسمنت بورتلاندي,حديد تسليح\n2025-01,100,2400\n2025-02,101.5,\"2,450\"\nسيء,1,2\n2025-03,abc,2500\n");
        $r = $p->parse($f, 'csv', 'M');
        $this->assertSame(['2025-01' => 100.0, '2025-02' => 101.5], $r['series']['إسمنت بورتلاندي']);
        $this->assertSame(2450.0, $r['series']['حديد تسليح']['2025-02']);
        $this->assertCount(2, $r['warnings']);
    }

    public function test_upload_endpoint_creates_series_attachable_to_a_node(): void
    {
        $this->artisan('chains:seed', ['slug' => 'ready-mix-concrete'])->assertSuccessful();
        $file = UploadedFile::fake()->createWithContent('gastat.csv', "period,Cement price index\n2025-01,101\n2025-02,103\n");
        $this->actingAs(User::create(['name' => 'x', 'email' => uniqid('p').'@example.com', 'password' => 'correct-horse-battery']))
            ->post('/api/v1/intel/uploads', ['file' => $file, 'source_name' => 'GASTAT', 'entity_code' => 'SAU', 'frequency' => 'M'], ['Accept' => 'application/json'])->assertForbidden();

        $admin = $this->admin();
        $res = $this->actingAs($admin)->post('/api/v1/intel/uploads', ['file' => $file, 'source_name' => 'GASTAT', 'source_url' => 'https://www.stats.gov.sa', 'entity_code' => 'SAU', 'unit' => 'مؤشر', 'frequency' => 'M'], ['Accept' => 'application/json'])->assertCreated();
        $key = $res->json('series.0.key');
        $this->assertSame('upload.gastat.cement_price_index', $key);
        $this->assertSame(103.0, (new SeriesReader)->latest($key, 'SAU')['value']);

        $node = DB::table('intel.chain_nodes')->where('slug', 'cement')->value('id');
        $this->actingAs($admin)->postJson("/api/v1/intel/chains/ready-mix-concrete/nodes/{$node}/series", [
            'indicator_key' => $key, 'entity_code' => 'SAU', 'label_ar' => 'مؤشر سعر الإسمنت (GASTAT)', 'price_type' => 'index', 'confidence' => 'high', 'role' => 'alternative',
        ])->assertOk();
        $full = $this->actingAs($admin)->getJson('/api/v1/intel/chains/ready-mix-concrete')->json();
        $s = collect(collect($full['nodes'])->firstWhere('slug', 'cement')['series'])->firstWhere('indicator_key', $key);
        $this->assertStringContainsString('رفع يدوي: GASTAT', $s['latest']['dataset']);
        $this->actingAs($admin)->getJson('/api/v1/intel/indicators?q=gastat')->assertJsonPath('data.0.key', $key);
    }

    public function test_cross_correlation_finds_known_lag_and_volatility(): void
    {
        mt_srand(11);
        $x = [];
        $y = [];
        $px = $py = 100.0;
        $shocks = [];
        $d = new \DateTimeImmutable('2016-01-01');
        for ($i = 0; $i < 96; $i++) {
            $m = $d->modify("+{$i} month")->format('Y-m');
            $shocks[$i] = (mt_rand() / mt_getrandmax() - 0.5) * 0.12;
            $px *= exp($shocks[$i]);
            $py *= exp(0.7 * ($shocks[$i - 3] ?? 0) + (mt_rand() / mt_getrandmax() - 0.5) * 0.01);
            $x[$m] = $px;
            $y[$m] = $py;
        }
        $a = new ChainAnalytics(new SeriesReader);
        $dl = fn (array $p) => (function () use ($p) {
            $o = [];
            $prev = null;
            foreach ($p as $k => $v) {
                if ($prev) {
                    $o[$k] = log($v) - log($prev);
                }
                $prev = $v;
            }

            return $o;
        })();
        $cc = $a->crossCorrelation($dl($y), $dl($x));
        $this->assertSame(3, $cc['best_lag']);
        $this->assertGreaterThan(0.8, $cc['best_corr']);
        $vol = $a->volatility($x);
        $this->assertEqualsWithDelta(0.12 / sqrt(12) * sqrt(12), $vol, 0.05, 'تقلب شهري منتظم ±6% ≈ 12% سنويًا تقريبًا');
    }

    public function test_analytics_and_history_endpoints(): void
    {
        $this->artisan('chains:seed', ['slug' => 'copper-power-cable'])->assertSuccessful();
        $admin = $this->admin();
        $this->actingAs($admin)->getJson('/api/v1/intel/chains/copper-power-cable/analytics')->assertOk()->assertJsonStructure(['final_node_id', 'nodes' => [['node_id', 'series', 'volatility', 'trade', 'risk', 'lag']]]);
        $series = DB::table('intel.price_series')->value('id');
        $this->actingAs($admin)->getJson("/api/v1/intel/chains/copper-power-cable/history?series_id={$series}")->assertOk()->assertJsonStructure(['series', 'points', 'latest']);
        $this->actingAs($admin)->getJson('/api/v1/intel/chains/copper-power-cable/history?series_id=999999')->assertNotFound();
    }
}
