<?php

namespace Tests\Feature;

use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\IngestRunner;
use App\Core\Ingestion\Promoter;
use App\Core\Insights\SignalMonitor;
use App\Models\Dataset;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\DatabaseTestCase;
use Tests\Support\FakeConnector;

class SignalMonitorTest extends DatabaseTestCase
{
    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->now = CarbonImmutable::parse('2026-10-15 12:00:00');
        config(['omanilab.connectors' => [FakeConnector::class], 'omanilab.telegram.token' => 'T', 'omanilab.telegram.chat_id' => '1']);
        FakeConnector::reset();
        $this->artisan('datasets:sync')->assertSuccessful();

        // سلسلة شهرية: قفزة 25% في سبتمبر، وسعر العام الماضي 8.
        FakeConnector::$custom = function () {
            yield new ParsedEntity('WLD', 'World', 'group');
            foreach (['2025-08' => 7.0, '2025-09' => 8.0, '2026-07' => 8.2, '2026-08' => 8.0, '2026-09' => 10.0] as $period => $v) {
                yield new ParsedRecord('fake.GDP', 'WLD', $period, $v);
            }
        };
        $run = app(IngestRunner::class)->run(Dataset::where('key', 'fake.test')->firstOrFail(), 'test');
        $this->assertSame('promoted', app(Promoter::class)->promote($run)->status, (string) $run->error);
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>|null
     */
    private function fire(array $rule): ?array
    {
        config(['signals.rules' => [$rule + ['id' => 'r', 'indicator' => 'fake.GDP', 'entity' => 'WLD', 'title' => 'ت', 'message' => '{value}|{previous}|{change}|{period}', 'tone' => 'warning', 'max_age_days' => 75]]]);

        return app(SignalMonitor::class)->active($this->now)[0] ?? null;
    }

    public function test_change_pct_fires_in_the_configured_direction_and_fills_the_template(): void
    {
        $s = $this->fire(['type' => 'change_pct', 'direction' => 'up', 'threshold' => 10.0]);

        $this->assertSame('10|8|25|سبتمبر 2026', $s['message']);
        $this->assertSame('2026-09', $s['period']);
        $this->assertNull($this->fire(['type' => 'change_pct', 'direction' => 'down', 'threshold' => 10.0]));
        $this->assertNull($this->fire(['type' => 'change_pct', 'direction' => 'any', 'threshold' => 30.0]));
    }

    public function test_change_abs_reports_the_absolute_move(): void
    {
        $s = $this->fire(['type' => 'change_abs', 'direction' => 'any', 'threshold' => 1.5]);

        $this->assertSame('10|8|2|سبتمبر 2026', $s['message']);
    }

    public function test_yoy_compares_with_the_same_month_last_year(): void
    {
        $s = $this->fire(['type' => 'yoy_pct', 'direction' => 'up', 'threshold' => 20.0]);

        $this->assertSame('10|8|25|سبتمبر 2026', $s['message']);
        $this->assertNull($this->fire(['type' => 'yoy_pct', 'direction' => 'up', 'threshold' => 30.0]));
    }

    public function test_levels_fire_above_or_below_a_threshold(): void
    {
        $this->assertNotNull($this->fire(['type' => 'above', 'threshold' => 9.9]));
        $this->assertNull($this->fire(['type' => 'above', 'threshold' => 10.0]));
        $this->assertNotNull($this->fire(['type' => 'below', 'threshold' => 10.1]));
        $this->assertNull($this->fire(['type' => 'below', 'threshold' => 10.0]));
    }

    public function test_a_stale_last_reading_gives_no_signal(): void
    {
        $this->now = $this->now->addMonths(4);

        $this->assertNull($this->fire(['type' => 'above', 'threshold' => 1.0]));
    }

    public function test_unknown_series_and_unknown_types_give_no_signal(): void
    {
        $this->assertNull($this->fire(['type' => 'bogus', 'threshold' => 1.0]));
        $this->assertNull($this->fire(['type' => 'above', 'threshold' => 1.0, 'indicator' => 'nope.X']));
    }

    public function test_the_command_sends_each_signal_once_per_period(): void
    {
        $this->freshHttp();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        config(['signals.rules' => [['id' => 'hot', 'indicator' => 'fake.GDP', 'entity' => 'WLD', 'type' => 'above', 'threshold' => 9.0, 'tone' => 'good', 'max_age_days' => 100000, 'title' => 'مرتفع', 'message' => '{value}']]]);

        $this->artisan('signals:check')->expectsOutput('إشارات أُرسلت: 1')->assertSuccessful();
        $this->artisan('signals:check')->expectsOutput('إشارات أُرسلت: 0')->assertSuccessful();

        Http::assertSentCount(1);
    }

    public function test_the_api_lists_active_signals(): void
    {
        config(['signals.rules' => [['id' => 'hot', 'indicator' => 'fake.GDP', 'entity' => 'WLD', 'type' => 'above', 'threshold' => 9.0, 'tone' => 'good', 'max_age_days' => 100000, 'title' => 'مرتفع', 'message' => '{value}']]]);

        $this->getJson('/api/v1/insights/signals')->assertOk()->assertJsonPath('data.0.id', 'hot')->assertJsonPath('data.0.tone', 'good');
    }
}
