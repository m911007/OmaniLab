<?php

namespace Tests\Unit;

use App\Core\Insights\Standing;
use PHPUnit\Framework\TestCase;

class StandingTest extends TestCase
{
    public function test_orientation_follows_the_indicator_polarity(): void
    {
        $this->assertSame(5.0, Standing::orient(5, 'higher'));
        $this->assertSame(-5.0, Standing::orient(5, 'lower'));
        $this->assertSame(-1.5, Standing::orient(3.5, 'target:2'));
        $this->assertSame(-1.5, Standing::orient(0.5, 'target:2'));
    }

    public function test_position_ranks_and_scales_the_percentile_by_polarity(): void
    {
        $values = ['OMN' => 5.0, 'A' => 1.0, 'B' => 9.0, 'C' => 5.0, 'D' => 7.0];

        $higher = Standing::position('OMN', $values, 'higher');
        $lower = Standing::position('OMN', $values, 'lower');

        $this->assertSame(['rank' => 3, 'of' => 5, 'percentile' => 37.5], $higher);
        $this->assertSame(['rank' => 2, 'of' => 5, 'percentile' => 62.5], $lower);
        $this->assertNull(Standing::position('XXX', $values, 'higher'));
        $this->assertNull(Standing::position('OMN', ['OMN' => 1.0], 'higher'));
    }

    public function test_first_place_is_the_hundredth_percentile(): void
    {
        $p = Standing::position('OMN', ['OMN' => 10.0, 'A' => 1.0, 'B' => 2.0], 'higher');

        $this->assertSame(['rank' => 1, 'of' => 3, 'percentile' => 100.0], $p);
    }

    public function test_trend_compares_the_oriented_change_with_the_spread_across_countries(): void
    {
        $spread = [1.0, 3.0, 5.0, 7.0, 9.0];

        $this->assertSame('improving', Standing::trend(7.0, 5.0, $spread, 'higher'));
        $this->assertSame('worsening', Standing::trend(7.0, 5.0, $spread, 'lower'));
        $this->assertSame('stable', Standing::trend(5.1, 5.0, $spread, 'higher'));
        $this->assertNull(Standing::trend(5.0, null, $spread, 'higher'));
        $this->assertNull(Standing::trend(5.0, 4.0, [1.0, 2.0], 'higher'));
        $this->assertSame('stable', Standing::trend(5.0, 4.0, [3.0, 3.0, 3.0], 'higher'));
    }

    public function test_classification_combines_standing_and_direction(): void
    {
        $this->assertSame(Standing::STRENGTH, Standing::classify(80, 'improving'));
        $this->assertSame(Standing::STRENGTH, Standing::classify(80, null));
        $this->assertSame(Standing::ERODING, Standing::classify(80, 'worsening'));
        $this->assertSame(Standing::CHALLENGE, Standing::classify(20, 'stable'));
        $this->assertSame(Standing::CHALLENGE, Standing::classify(20, 'worsening'));
        $this->assertSame(Standing::RECOVERING, Standing::classify(20, 'improving'));
        $this->assertSame(Standing::OPPORTUNITY, Standing::classify(50, 'improving'));
        $this->assertSame(Standing::WATCH, Standing::classify(50, 'worsening'));
        $this->assertSame(Standing::NEUTRAL, Standing::classify(50, 'stable'));
        $this->assertSame(Standing::STRENGTH, Standing::classify(Standing::STRONG_FROM, 'stable'));
        $this->assertSame(Standing::CHALLENGE, Standing::classify(Standing::WEAK_UNTIL, 'stable'));
    }
}
