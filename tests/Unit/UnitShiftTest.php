<?php

namespace Tests\Unit;

use App\Core\Quality\UnitShift;
use PHPUnit\Framework\TestCase;

class UnitShiftTest extends TestCase
{
    public function test_points_published_in_billions_are_rescaled_to_millions(): void
    {
        $points = [
            '2023-Q1' => 25996.2, '2023-Q2' => 25338.1, '2023-Q3' => 26177.6, '2023-Q4' => 28383.9,
            '2024-Q2' => 26.3, '2024-Q3' => 27.2,
        ];

        $fixed = UnitShift::fix($points, 'gccstat.GDP_CUR_Q', 'OMN', record: false);

        $this->assertEqualsWithDelta(26300.0, $fixed['2024-Q2'], 0.001);
        $this->assertEqualsWithDelta(27200.0, $fixed['2024-Q3'], 0.001);
        $this->assertSame(25996.2, $fixed['2023-Q1']);
    }

    public function test_genuine_moves_short_series_and_one_off_dips_are_left_alone(): void
    {
        $falling = ['2019' => 100.0, '2020' => 90.0, '2021' => 80.0, '2022' => 70.0, '2023' => 5.0];
        $short = ['2022' => 25000.0, '2023' => 25.0];
        // رصيد يقترب من الصفر لسنة ثم يعود: لا استمرار على المستوى الجديد، فلا تصحيح
        $dip = ['2018' => 5000.0, '2019' => 5200.0, '2020' => 4800.0, '2021' => 5100.0, '2022' => 5.0, '2023' => 4900.0];

        $this->assertSame($falling, UnitShift::fix($falling, 'x', 'OMN', record: false));
        $this->assertSame($short, UnitShift::fix($short, 'x', 'OMN', record: false));
        $this->assertSame($dip, UnitShift::fix($dip, 'x', 'OMN', record: false));
    }

    public function test_a_last_point_published_in_thousands_is_restored(): void
    {
        // اشتراكات الكهرباء: 2025 نُشرت بالآلاف
        $points = ['2021' => 1359700.0, '2022' => 1386233.0, '2023' => 1449485.0, '2024' => 1497669.0, '2025' => 1544.03];

        $this->assertEqualsWithDelta(1544030.0, UnitShift::fix($points, 'ncsi.ELEC_CONNECTIONS', 'OMN', record: false)['2025'], 0.01);
    }
}
