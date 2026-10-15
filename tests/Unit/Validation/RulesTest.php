<?php

namespace Tests\Unit\Validation;

use App\Core\Validation\Issue;
use App\Core\Validation\Rules\Completeness;
use App\Core\Validation\Rules\Freshness;
use App\Core\Validation\Rules\MaxRevision;
use App\Core\Validation\Rules\Range;
use App\Core\Validation\Rules\RelativeRevision;
use App\Core\Validation\Rules\SeriesScale;
use App\Core\Validation\Rules\SeriesSpike;
use App\Core\Validation\Rules\WellFormed;
use App\Core\Validation\ValidationContext;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class RulesTest extends TestCase
{
    /**
     * @param  array<int, array{0:string,1:string,2:float|int}>  $rows
     * @param  array<string, float>  $previous
     * @param  list<string>  $known
     */
    private function ctx(array $rows, array $previous = [], int $previousCount = 0, array $known = ['x.A']): ValidationContext
    {
        $records = array_map(fn ($r) => ['indicator' => 'x.A', 'entity' => $r[0], 'period' => $r[1], 'value' => $r[2]], $rows);

        return new ValidationContext('x.A', $records, $previous, $previousCount, $known);
    }

    /** @return list<Issue> */
    private function issuesOf(object $rule, ValidationContext $ctx): array
    {
        return iterator_to_array($rule->check($ctx), false);
    }

    public function test_range_flags_values_outside_bounds_as_errors(): void
    {
        $issues = $this->issuesOf(new Range(0, 100), $this->ctx([['OMN', '2024', 50], ['OMN', '2025', 101.5], ['OMN', '2026', -1]]));

        $this->assertCount(2, $issues);
        $this->assertTrue($issues[0]->isError());
        $this->assertSame('2025', $issues[0]->period);
    }

    public function test_max_revision_warns_on_isolated_large_revisions(): void
    {
        $previous = [];
        $rows = [];
        for ($i = 0; $i < 10; $i++) {
            $previous["C{$i}|2024"] = 1.0;
            $rows[] = ["C{$i}", '2024', $i === 0 ? 9.0 : 1.1]; // نقطة واحدة من عشر تغيّرت بشدة
        }

        $issues = $this->issuesOf(new MaxRevision(2.0, 0.25), $this->ctx($rows, $previous));

        $this->assertCount(1, $issues);
        $this->assertSame('warning', $issues[0]->severity);
    }

    public function test_max_revision_errors_when_a_systematic_share_changes(): void
    {
        $previous = [];
        $rows = [];
        for ($i = 0; $i < 10; $i++) {
            $previous["C{$i}|2024"] = 1.0;
            $rows[] = ["C{$i}", '2024', 100.0]; // كل النقاط قفزت: وحدة القياس تغيّرت غالبًا
        }

        $issues = $this->issuesOf(new MaxRevision(2.0, 0.25), $this->ctx($rows, $previous));

        $this->assertCount(1, $issues);
        $this->assertTrue($issues[0]->isError());
    }

    public function test_max_revision_ignores_points_without_history(): void
    {
        $this->assertSame([], $this->issuesOf(new MaxRevision(1.0), $this->ctx([['OMN', '2030', 999]])));
    }

    public function test_completeness_errors_when_points_disappear(): void
    {
        $issues = $this->issuesOf(new Completeness(0.1), $this->ctx([['OMN', '2024', 1]], previousCount: 100));
        $this->assertTrue($issues[0]->isError());

        $this->assertSame([], $this->issuesOf(new Completeness(0.1), $this->ctx([['OMN', '2024', 1]], previousCount: 0)));
    }

    public function test_well_formed_rejects_empty_payload_bad_period_and_unknown_indicator(): void
    {
        $this->assertTrue($this->issuesOf(new WellFormed, $this->ctx([]))[0]->isError());

        $bad = $this->issuesOf(new WellFormed, $this->ctx([['OMN', 'last-year', 1.0]]));
        $this->assertNotEmpty($bad);

        $unknown = $this->issuesOf(new WellFormed, new ValidationContext(null, [['indicator' => 'y.B', 'entity' => 'OMN', 'period' => '2024', 'value' => 1.0]], [], 0, ['x.A']));
        $this->assertStringContainsString('y.B', $unknown[0]->message);

        $this->assertSame([], $this->issuesOf(new WellFormed, $this->ctx([['OMN', '2024', 1.0], ['OMN', '2024-Q3', 2.0], ['OMN', '2024-09', 2.0]])));
    }

    public function test_freshness_rejects_an_older_release(): void
    {
        $older = new ValidationContext(null, [], [], 0, [], CarbonImmutable::parse('2026-04-01'), CarbonImmutable::parse('2026-10-01'));
        $newer = new ValidationContext(null, [], [], 0, [], CarbonImmutable::parse('2026-10-08'), CarbonImmutable::parse('2026-10-01'));

        $this->assertTrue($this->issuesOf(new Freshness, $older)[0]->isError());
        $this->assertSame([], $this->issuesOf(new Freshness, $newer));
    }

    public function test_range_with_reject_severity_drops_the_point_instead_of_failing_the_run(): void
    {
        $issues = $this->issuesOf(new Range(0, 100, 'reject'), $this->ctx([['OMN', '2024', 500]]));

        $this->assertTrue($issues[0]->isReject());
        $this->assertFalse($issues[0]->isError());
    }

    public function test_series_spike_rejects_a_unit_glitch_between_normal_neighbours(): void
    {
        $rows = [];
        foreach ([2010 => 125.0, 2011 => 167.8, 2012 => 186.8, 2013 => 125122.5, 2014 => 190.0, 2015 => 195.0, 2016 => 201.0] as $y => $v) {
            $rows[] = ['QAT', (string) $y, $v];
        }

        $issues = $this->issuesOf(new SeriesSpike(50), $this->ctx($rows));

        $this->assertCount(1, $issues);
        $this->assertSame('2013', $issues[0]->period);
        $this->assertTrue($issues[0]->isReject());
    }

    public function test_series_spike_ignores_smooth_growth_even_over_many_years(): void
    {
        $rows = [];
        $v = 1.0;
        foreach (range(1960, 2024) as $y) {
            $rows[] = ['OMN', (string) $y, $v *= 1.12]; // نمو 12% سنويًا → آلاف الأضعاف عبر 65 سنة، ولا قفزة
        }

        $this->assertSame([], $this->issuesOf(new SeriesSpike(50), $this->ctx($rows)));
    }

    public function test_series_scale_catches_a_whole_block_in_the_wrong_unit(): void
    {
        $rows = [];
        foreach (range(1, 12) as $m) {
            $rows[] = ['OMN', sprintf('2014-%02d', $m), 1_000_000.0 + $m]; // كتلة بالبرميل
            $rows[] = ['OMN', sprintf('2016-%02d', $m), 1000.0 + $m]; // الباقي بآلاف البراميل
        }
        foreach (range(1, 12) as $m) {
            $rows[] = ['OMN', sprintf('2017-%02d', $m), 1010.0 + $m];
        }

        $issues = $this->issuesOf(new SeriesScale(100), $this->ctx($rows));

        $this->assertCount(12, $issues, 'كتلة 2014 كلها مرفوضة، وكتلة 2016/2017 سليمة');
        $this->assertSame(['2014-01', '2014-12'], [$issues[0]->period, $issues[11]->period]);
        $this->assertTrue($issues[0]->isReject());
    }

    public function test_series_scale_needs_enough_points_to_judge(): void
    {
        $this->assertSame([], $this->issuesOf(new SeriesScale(100, 6), $this->ctx([['OMN', '2024', 1.0], ['OMN', '2025', 99999.0]])));
    }

    public function test_relative_revision_uses_proportions_not_absolute_differences(): void
    {
        $previous = ['OMN|2024' => 1_000_000.0];

        // 40 ألفًا فرق مطلق كبير لكنه 4% فقط: مقبول
        $this->assertSame([], $this->issuesOf(new RelativeRevision(0.5), $this->ctx([['OMN', '2024', 1_040_000.0]], $previous)));

        $two = ['OMN|2024' => 1_000_000.0, 'SAU|2024' => 1_000_000.0];
        $warn = $this->issuesOf(new RelativeRevision(0.5, 0.9), $this->ctx([['OMN', '2024', 2_000_000.0], ['SAU', '2024', 1_010_000.0]], $two));
        $this->assertSame('warning', $warn[0]->severity, 'نقطة واحدة من اثنتين: حصة 50% دون حد الخطأ');

        $err = $this->issuesOf(new RelativeRevision(0.5, 0.25), $this->ctx([['OMN', '2024', 2_000_000.0]], $previous));
        $this->assertTrue($err[0]->isError());
    }
}
