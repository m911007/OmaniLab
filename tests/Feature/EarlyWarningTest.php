<?php

namespace Tests\Feature;

use App\Modules\ValueChain\ChainAnalytics;
use App\Modules\ValueChain\EarlyWarning;
use App\Modules\ValueChain\SeriesReader;
use App\Modules\ValueChain\WeightValidator;
use Tests\DatabaseTestCase;

class EarlyWarningTest extends DatabaseTestCase
{
    public function test_forecast_carries_already_observed_input_moves_with_lag(): void
    {
        mt_srand(5);
        $x = [];
        $y = [];
        $px = $py = 100.0;
        $sh = [];
        $start = new \DateTimeImmutable('2017-01-01');
        for ($i = 0; $i < 100; $i++) {
            $m = $start->modify("+{$i} month")->format('Y-m');
            $sh[$i] = (mt_rand() / mt_getrandmax() - 0.5) * 0.08;
            if ($i === 98) {
                $sh[$i] = 0.10; // قفزة 10% في المدخل قبل شهرين من النهاية
            }
            $px *= exp($sh[$i]);
            $py *= exp(0.6 * ($sh[$i - 3] ?? 0) + (mt_rand() / mt_getrandmax() - 0.5) * 0.004);
            $x[$m] = $px;
            $y[$m] = $py;
        }
        $ew = new EarlyWarning(new ChainAnalytics(new SeriesReader), new WeightValidator);
        $f = $ew->forecast(['key' => 'y', 'label_ar' => 'منتج', 'points' => $y], [['node' => (object) ['name_ar' => 'نحاس'], 'weight' => 0.6, 'series' => ['key' => 'x', 'label_ar' => 'x', 'points' => $x]]]);

        $h = collect($f['horizons'])->keyBy('horizon_months');
        // ما سينتقل خلال 3 أشهر = 0.6 × (صدمات المدخل في آخر ثلاثة أشهر، ومنها القفزة 10%)، ولا شيء إضافي بعدها
        $expected = (exp(0.6 * ($sh[97] + $sh[98] + $sh[99])) - 1) * 100;
        $this->assertEqualsWithDelta($expected, $h[3]['expected_pct'], 0.6);
        $this->assertEqualsWithDelta($h[3]['expected_pct'], $h[6]['expected_pct'], 1.0);
        $this->assertEqualsWithDelta((exp(0.6 * $sh[97]) - 1) * 100, $h[1]['expected_pct'], 0.6, 'الشهر القادم يحمل صدمة ما قبل 3 أشهر فقط');
        $this->assertSame('نحاس', $h[3]['drivers'][0]['input']);
        $this->assertGreaterThan($h[3]['expected_pct'], $h[3]['high_pct']);
        $this->assertGreaterThan(0.9, $f['fit']['r2']);
    }

    public function test_shift_and_dlog(): void
    {
        $this->assertSame('2025-11', EarlyWarning::shift('2026-01', 2));
        $this->assertSame('2026-03', EarlyWarning::shift('2026-01', -2));
        $d = EarlyWarning::dlog(['2025-01' => 100, '2025-02' => 110, '2025-04' => 120]);
        $this->assertSame(['2025-02'], array_keys($d), 'الفجوة الشهرية لا تُحسب تغيرًا');
    }
}
