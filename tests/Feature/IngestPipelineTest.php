<?php

namespace Tests\Feature;

use App\Core\Freshness\DatasetStatus;
use App\Core\Freshness\FreshnessService;
use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\DefinitionSync;
use App\Core\Ingestion\IngestRunner;
use App\Core\Ingestion\Jobs\IngestDatasetJob;
use App\Core\Ingestion\Promoter;
use App\Core\Ingestion\RunJanitor;
use App\Models\Dataset;
use App\Models\IngestRun;
use App\Modules\Companies\Connectors\GleifLeiCountsConnector;
use App\Modules\Macro\Connectors\ImfWeoConnector;
use App\Modules\Manafeth\Connectors\ManafethPartnersConnector;
use App\Modules\Wits\Connectors\WitsTradeConnector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\DatabaseTestCase;
use Tests\Support\FakeConnector;
use Tests\Support\RivalFakeConnector;

class IngestPipelineTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'omanilab.connectors' => [FakeConnector::class],
            'omanilab.telegram.token' => 'T', 'omanilab.telegram.chat_id' => '1',
        ]);
        FakeConnector::reset();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $this->artisan('datasets:sync')->assertSuccessful();
    }

    private function dataset(): Dataset
    {
        return Dataset::where('key', 'fake.test')->firstOrFail();
    }

    /** يشغّل المرحلتين كما يفعل العاملان. */
    private function ingest(bool $force = false): IngestRun
    {
        $run = app(IngestRunner::class)->run($this->dataset(), 'test', $force);

        return $run->status === 'staged' ? app(Promoter::class)->promote($run) : $run;
    }

    private function observationCount(): int
    {
        return DB::table('core.observations')->count();
    }

    /** @return list<string> */
    private function telegramMessages(): array
    {
        return Http::recorded(fn ($req) => str_contains($req->url(), 'telegram'))->map(fn ($pair) => $pair[0]['text'])->all();
    }

    public function test_sync_creates_dataset_pending_and_due_immediately(): void
    {
        $d = $this->dataset();

        $this->assertSame(DatasetStatus::Pending, $d->status);
        $this->assertNotNull($d->next_check_at);
        $this->assertSame(1, DB::table('core.indicators')->count());
    }

    public function test_first_run_promotes_and_records_freshness(): void
    {
        $run = $this->ingest();

        $this->assertSame('promoted', $run->status);
        $this->assertSame(6, $this->observationCount());
        $this->assertSame(0, DB::table('staging.records')->count(), 'staging يُنظَّف بعد الاعتماد');

        $d = $this->dataset();
        $this->assertSame(DatasetStatus::Fresh, $d->status);
        $this->assertSame('Fake Outlook v1', $d->source_version);
        $this->assertNotNull($d->last_checked_at);
        $this->assertNotNull($d->last_changed_at);
        $this->assertEquals($d->last_checked_at->addMinutes(60)->timestamp, $d->next_check_at->timestamp);
        $this->assertSame(1, DB::table('raw.payloads')->count(), 'الحمولة الخام محفوظة');
    }

    public function test_same_probe_token_skips_fetch_and_only_updates_last_checked(): void
    {
        $this->ingest();
        $changedAt = $this->dataset()->last_changed_at;
        $this->travel(30)->minutes();

        $run = $this->ingest();

        $this->assertSame('unchanged', $run->status);
        $this->assertSame('probe', $run->meta['unchanged_by']);
        $this->assertSame(1, DB::table('raw.payloads')->count(), 'لم تُجلب حمولة جديدة');
        $d = $this->dataset();
        $this->assertEquals($changedAt->timestamp, $d->last_changed_at->timestamp, 'آخر تغيير لم يتحرك');
        $this->assertGreaterThan($changedAt->timestamp, $d->last_checked_at->timestamp, 'آخر فحص تحرّك');
    }

    public function test_new_token_with_identical_payload_is_unchanged_by_hash(): void
    {
        $this->ingest();
        FakeConnector::$token = 'v2'; // المصدر تغيّرت بصمته لكن البيانات نفسها

        $run = $this->ingest();

        $this->assertSame('unchanged', $run->status);
        $this->assertSame('hash', $run->meta['unchanged_by']);
        $this->assertSame('v2#r1', $this->dataset()->probe_token, 'تُحفظ البصمة الجديدة (مع مراجعة المحلّل) ليقصّر الفحص التالي');
    }

    public function test_revision_updates_only_changed_points(): void
    {
        $this->ingest();
        FakeConnector::$token = 'v2';
        FakeConnector::$version = 'Fake Outlook v2';
        FakeConnector::$publishedAt = '2026-10-08 00:00:00';
        FakeConnector::$data['OMN']['2025'] = 3.0; // مراجعة طبيعية صغيرة (+0.6)
        FakeConnector::$data['OMN']['2026'] = 3.2; // نقطة جديدة

        $run = $this->ingest();

        $this->assertSame('promoted', $run->status);
        $this->assertSame(2, $run->promoted_count);
        $this->assertSame(7, $this->observationCount());
        $this->assertSame('Fake Outlook v2', $this->dataset()->source_version);
        $this->assertSame(3.0, (float) DB::table('core.observations')->where('period', '2025')->where('entity_id', DB::table('core.entities')->where('code', 'OMN')->value('id'))->value('value'));
    }

    public function test_out_of_range_value_quarantines_and_keeps_last_good_data(): void
    {
        $this->ingest();
        $before = DB::table('core.observations')->orderBy('id')->pluck('value', 'id')->all();

        FakeConnector::$token = 'v2';
        FakeConnector::$data['OMN']['2025'] = 999.0;

        $run = $this->ingest();

        $this->assertSame('quarantined', $run->status);
        $this->assertSame($before, DB::table('core.observations')->orderBy('id')->pluck('value', 'id')->all(), 'core لم يُمسّ');
        $d = $this->dataset();
        $this->assertSame(DatasetStatus::Quarantined, $d->status);
        $this->assertSame('v1#r1', $d->probe_token, 'البصمة المعتمدة لم تتغير');
        $this->assertSame(1, DB::table('ops.validation_issues')->where('run_id', $run->id)->where('severity', 'error')->count());
        $this->assertCount(1, $this->telegramMessages());
        $this->assertStringContainsString('بيانات محجوزة', $this->telegramMessages()[0]);
    }

    public function test_systematic_change_is_quarantined_by_revision_rule(): void
    {
        $this->ingest();
        FakeConnector::$token = 'v2';
        foreach (FakeConnector::$data as $e => $series) {
            foreach ($series as $p => $v) {
                FakeConnector::$data[$e][$p] = $v + 20; // انزياح منهجي ضمن النطاق لكن مريب
            }
        }

        $run = $this->ingest();

        $this->assertSame('quarantined', $run->status);
        $this->assertStringContainsString('خلل منهجي', $run->error);
    }

    public function test_missing_points_quarantine_via_completeness(): void
    {
        $this->ingest();
        FakeConnector::$token = 'v2';
        FakeConnector::$data = ['OMN' => ['2025' => 2.4]]; // اختفت دولة وسنوات

        $run = $this->ingest();

        $this->assertSame('quarantined', $run->status);
        $this->assertSame(6, $this->observationCount());
    }

    public function test_same_bad_payload_is_not_reprocessed_but_force_recovers_after_fix(): void
    {
        $this->ingest();
        FakeConnector::$token = 'v2';
        FakeConnector::$data['OMN']['2025'] = 999.0;
        $this->ingest();

        $second = $this->ingest();
        $this->assertSame('unchanged', $second->status);
        $this->assertSame('quarantined_same', $second->meta['unchanged_by']);
        $this->assertSame(DatasetStatus::Quarantined, $this->dataset()->status);

        FakeConnector::$data['OMN']['2025'] = 2.5; // المصدر صحّح الخطأ
        FakeConnector::$token = 'v3';
        $third = $this->ingest();
        $this->assertSame('promoted', $third->status);
        $this->assertSame(DatasetStatus::Fresh, $this->dataset()->status);
        $this->assertStringContainsString('عادت للعمل', implode(' ', $this->telegramMessages()));
    }

    public function test_older_release_is_rejected(): void
    {
        $this->ingest();
        FakeConnector::$token = 'v0';
        FakeConnector::$publishedAt = '2026-04-01 00:00:00';
        FakeConnector::$data['OMN']['2024'] = 1.7;

        $run = $this->ingest();

        $this->assertSame('quarantined', $run->status);
        $this->assertStringContainsString('أقدم', $run->error);
    }

    public function test_fetch_failure_is_recorded_with_backoff_and_alert_after_threshold(): void
    {
        $this->ingest();
        FakeConnector::$failWith = 'connection reset';

        $this->ingest();
        $d = $this->dataset();
        $this->assertSame(1, $d->consecutive_failures);
        $this->assertSame(DatasetStatus::Fresh, $d->status, 'إخفاق واحد لا يغيّر الحالة');
        $this->assertEqualsWithDelta(now()->addMinutes(5)->timestamp, $d->next_check_at->timestamp, 5, 'تأخير 5 دقائق');
        $this->assertSame('connection reset', $d->last_error);
        $this->assertCount(0, $this->telegramMessages());

        $this->ingest();
        $this->ingest();
        $d = $this->dataset();
        $this->assertSame(3, $d->consecutive_failures);
        $this->assertSame(DatasetStatus::Failing, $d->status);
        $this->assertStringContainsString('فشل الجلب', $this->telegramMessages()[0]);
        $this->assertSame(6, $this->observationCount(), 'البيانات المعروضة سليمة رغم الفشل');
    }

    public function test_recovery_after_failure_resets_counters_and_alerts(): void
    {
        $this->ingest();
        FakeConnector::$failWith = 'boom';
        $this->ingest();
        $this->ingest();
        $this->ingest();
        FakeConnector::$failWith = null;

        $run = $this->ingest();

        $this->assertSame('unchanged', $run->status);
        $d = $this->dataset();
        $this->assertSame(0, $d->consecutive_failures);
        $this->assertSame(DatasetStatus::Fresh, $d->status);
        $this->assertNull($d->last_error);
        $this->assertStringContainsString('عادت للعمل', implode(' ', $this->telegramMessages()));
    }

    public function test_stale_sweep_marks_overdue_datasets_and_alerts_once(): void
    {
        $this->ingest();
        $this->travel(91)->minutes(); // الدورة 60 دقيقة × 1.5 = 90

        $this->assertSame(1, app(FreshnessService::class)->sweepStale());
        $this->assertSame(DatasetStatus::Stale, $this->dataset()->status);
        $this->assertSame(0, app(FreshnessService::class)->sweepStale(), 'لا يتكرر');
        $this->assertCount(1, $this->telegramMessages());
    }

    public function test_identical_alerts_are_deduplicated(): void
    {
        $this->ingest();
        FakeConnector::$token = 'v2';
        FakeConnector::$data['OMN']['2025'] = 999.0;
        $this->ingest();
        FakeConnector::$token = 'v3';
        FakeConnector::$data['OMN']['2025'] = 998.0;
        $this->ingest();

        $this->assertSame(2, IngestRun::where('status', 'quarantined')->count());
        $this->assertCount(1, $this->telegramMessages(), 'تنبيه واحد خلال فترة منع التكرار');
    }

    public function test_dispatch_command_queues_only_due_datasets(): void
    {
        Queue::fake();

        $this->artisan('datasets:dispatch')->assertSuccessful();
        Queue::assertPushedOn('ingest', IngestDatasetJob::class);

        Queue::fake();
        $this->artisan('datasets:dispatch')->assertSuccessful(); // حُجزت 30 دقيقة، فلا إرسال مزدوج
        Queue::assertNothingPushed();
    }

    public function test_a_rejected_point_is_dropped_and_logged_but_the_run_still_promotes(): void
    {
        FakeConnector::$data = ['OMN' => ['2018' => 0.10, '2019' => 0.11, '2020' => 0.12, '2021' => 0.13, '2022' => 0.14, '2023' => 0.15, '2024' => 0.16, '2025' => 0.17]];
        $this->ingest();
        FakeConnector::$token = 'v2';
        FakeConnector::$data['OMN']['2021'] = 45.0; // ضمن النطاق المطلق لكنها 350× وسيط السلسلة: خلل وحدة

        $run = $this->ingest();

        $this->assertSame('promoted', $run->status, 'النقطة المعيبة لا تحجز بقية البيانات');
        $this->assertSame(1, $run->meta['rejected_points']);
        $omn = DB::table('core.entities')->where('code', 'OMN')->value('id');
        $this->assertSame(0.13, (float) DB::table('core.observations')->where('entity_id', $omn)->where('period', '2021')->value('value'), 'بقيت القيمة السليمة السابقة');
        $issue = DB::table('ops.validation_issues')->where('run_id', $run->id)->where('severity', 'reject')->first();
        $this->assertSame('2021', $issue->period);
        $this->assertSame('series_scale', $issue->rule);
        $this->assertCount(0, $this->telegramMessages(), 'الاستبعاد الدقيق لا يزعج بتنبيه');
    }

    public function test_source_without_cheap_probe_is_always_fetched_and_skipped_by_payload_hash(): void
    {
        FakeConnector::$noCheapProbe = true;
        $this->ingest();

        $second = $this->ingest();
        $this->assertSame('unchanged', $second->status);
        $this->assertSame('hash', $second->meta['unchanged_by'], 'جُلبت الحمولة وقورنت بصمتها، ولم يُقصَّر الفحص');
        $this->assertNull($this->dataset()->probe_token);

        FakeConnector::$data['OMN']['2025'] = 2.9;
        $third = $this->ingest();
        $this->assertSame('promoted', $third->status);
        $this->assertSame(1, $third->promoted_count);
    }

    public function test_identical_duplicates_inside_a_payload_are_collapsed_silently(): void
    {
        $run = $this->ingestWith(function () {
            yield from FakeConnector::items();
            yield new ParsedRecord('fake.GDP', 'OMN', '2024', 1.6); // نفسها حرفيًا
            yield new ParsedRecord('fake.GDP', 'omn', '2024', 1.6); // نفس الكيان بحروف صغيرة
        });

        $this->assertSame('promoted', $run->status);
        $this->assertSame(2, $run->meta['duplicates_collapsed']);
        $this->assertSame(6, $this->observationCount());
    }

    public function test_conflicting_duplicates_quarantine_the_run(): void
    {
        $run = $this->ingestWith(function () {
            yield from FakeConnector::items();
            yield new ParsedRecord('fake.GDP', 'OMN', '2024', 9.9); // نفس المفتاح بقيمة مختلفة
        });

        $this->assertSame('quarantined', $run->status);
        $this->assertStringContainsString('بقيمتين مختلفتين', $run->error);
        $this->assertSame(0, $this->observationCount());
    }

    public function test_aliased_entity_codes_are_unified_and_names_cleaned_on_the_way_in(): void
    {
        $run = $this->ingestWith(function () {
            yield new ParsedEntity('UVK', 'KOSOVO', 'country');
            yield new ParsedRecord('fake.GDP', 'UVK', '2023', 1.0);
            yield new ParsedRecord('fake.GDP', 'XKX', '2024', 1.1);
        });

        $this->assertSame('promoted', $run->status);
        $this->assertSame(['XKX'], DB::table('core.entities')->pluck('code')->all(), 'لا كيان UVK منفصل');
        $this->assertSame('Kosovo', DB::table('core.entities')->value('name_en'));
        $this->assertSame(2, $this->observationCount());
    }

    public function test_an_indicator_key_cannot_be_claimed_by_two_datasets(): void
    {
        config(['omanilab.connectors' => [FakeConnector::class, RivalFakeConnector::class]]);

        $this->expectExceptionMessage('fake.GDP');
        $this->artisan('datasets:sync');
        app(DefinitionSync::class)->sync();
    }

    /** يشغّل الخط على بنود يتحكم بها الاختبار مباشرة. @param  callable(): iterable<mixed>  $items */
    private function ingestWith(callable $items): IngestRun
    {
        FakeConnector::$custom = $items;

        return $this->ingest();
    }

    public function test_points_the_source_stopped_publishing_for_a_republished_period_are_removed(): void
    {
        $this->ingest();
        FakeConnector::$token = 'v2';
        unset(FakeConnector::$data['SAU']['2024']); // المصدر صحّح نفسه: لم يعد ينشر هذه النقطة

        $run = $this->ingest();

        $this->assertSame('promoted', $run->status);
        $this->assertSame(1, $run->meta['removed_points']);
        $sau = DB::table('core.entities')->where('code', 'SAU')->value('id');
        $this->assertSame(0, DB::table('core.observations')->where('entity_id', $sau)->where('period', '2024')->count());
        $this->assertSame(5, $this->observationCount());
    }

    public function test_periods_that_left_the_sources_window_are_kept_as_history(): void
    {
        $this->ingest();
        FakeConnector::$token = 'v2';
        FakeConnector::$data = ['OMN' => ['2025' => 2.4], 'SAU' => ['2025' => 3.0]]; // نافذة لم تعد تشمل 2023 و2024

        $run = $this->ingest();

        $this->assertSame('quarantined', $run->status, 'النقص الكبير في عدد النقاط يحجزها (اكتمال)، لا تُحذف تاريخيًا');
        $this->assertSame(6, $this->observationCount());
    }

    public function test_a_rejected_new_value_does_not_delete_the_previous_good_one(): void
    {
        FakeConnector::$data = ['OMN' => ['2018' => 0.10, '2019' => 0.11, '2020' => 0.12, '2021' => 0.13, '2022' => 0.14, '2023' => 0.15, '2024' => 0.16, '2025' => 0.17]];
        $this->ingest();
        FakeConnector::$token = 'v2';
        FakeConnector::$data['OMN']['2022'] = 45.0;

        $this->ingest();

        $omn = DB::table('core.entities')->where('code', 'OMN')->value('id');
        $this->assertSame(0.14, (float) DB::table('core.observations')->where('entity_id', $omn)->where('period', '2022')->value('value'));
        $this->assertSame(8, DB::table('core.observations')->where('entity_id', $omn)->count());
    }

    public function test_a_payload_that_would_wipe_out_a_large_share_of_data_is_quarantined(): void
    {
        $data = [];
        foreach (range(1, 300) as $i) {
            $data['E'.str_pad((string) $i, 3, '0', STR_PAD_LEFT)] = ['2024' => 1.0 + $i / 1000];
        }
        FakeConnector::$data = $data;
        $this->ingest();

        FakeConnector::$token = 'v2';
        FakeConnector::$data = array_slice($data, 0, 150, true); // نصف الكيانات اختفى من نفس الفترة

        $run = $this->ingest();

        $this->assertSame('quarantined', $run->status);
        $this->assertSame(300, $this->observationCount());
    }

    public function test_abandoned_runs_are_marked_failed_and_their_staging_rows_cleaned(): void
    {
        $dataset = $this->dataset();
        $old = IngestRun::create(['dataset_id' => $dataset->id, 'trigger' => 'schedule', 'status' => 'staged', 'started_at' => now()->subHour()]);
        $recent = IngestRun::create(['dataset_id' => $dataset->id, 'trigger' => 'schedule', 'status' => 'running', 'started_at' => now()->subMinutes(5)]);
        foreach ([$old, $recent] as $r) {
            DB::table('staging.records')->insert(['run_id' => $r->id, 'indicator_key' => 'fake.GDP', 'entity_code' => 'OMN', 'period' => '2024', 'value' => 1.0]);
        }

        $swept = app(RunJanitor::class)->sweep();

        $this->assertSame(1, $swept);
        $this->assertSame('failed', $old->refresh()->status);
        $this->assertStringContainsString('متروك', $old->error);
        $this->assertSame('running', $recent->refresh()->status, 'تشغيل حديث لا يُمس');
        $this->assertSame(0, DB::table('staging.records')->where('run_id', $old->id)->count());
        $this->assertSame(1, DB::table('staging.records')->where('run_id', $recent->id)->count());
    }

    public function test_a_dataset_removed_from_code_is_disabled_not_deleted_and_its_indicators_can_move(): void
    {
        $this->ingest();
        $this->assertSame(6, $this->observationCount());

        // المجموعة fake.test لم تعد معرّفة، وظهرت مجموعة جديدة تتبنى مؤشرها.
        config(['omanilab.connectors' => [RivalFakeConnector::class]]);
        $this->artisan('datasets:sync')->assertSuccessful();

        $old = Dataset::where('key', 'fake.test')->firstOrFail();
        $this->assertSame(DatasetStatus::Disabled, $old->status);
        $this->assertFalse($old->enabled);
        $this->assertSame(6, $this->observationCount(), 'البيانات المعتمدة لا تُحذف');
        $this->assertSame('fake.rival', DB::table('core.indicators as i')->join('ops.datasets as d', 'd.id', '=', 'i.dataset_id')->where('i.key', 'fake.GDP')->value('d.key'));

        Queue::fake();
        $this->artisan('datasets:dispatch')->assertSuccessful();
        Queue::assertPushed(IngestDatasetJob::class, 1); // المنافس الجديد فقط
    }

    public function test_bumping_the_parser_revision_reprocesses_even_when_the_source_did_not_change(): void
    {
        $this->ingest();
        $this->assertSame('unchanged', $this->ingest()->status, 'بلا تغيير في المصدر ولا في المحلّل');

        FakeConnector::$revision = 2; // المطوّر غيّر ما ينتجه parse()
        $run = $this->ingest();

        $this->assertSame('promoted', $run->status, 'أُعيدت المعالجة رغم ثبات المصدر');
        $this->assertSame('v1#r2', $this->dataset()->probe_token);
        $this->assertSame('unchanged', $this->ingest()->status, 'ثم يستقر ثانيةً');
    }

    public function test_heavy_sources_are_dispatched_to_their_own_queue(): void
    {
        FakeConnector::$queueName = 'ingest-heavy';
        Queue::fake();

        $this->artisan('datasets:dispatch')->assertSuccessful();

        Queue::assertPushed(IngestDatasetJob::class, fn ($job) => $job->queue === 'ingest-heavy');
        Queue::assertNotPushed(IngestDatasetJob::class, fn ($job) => $job->queue === 'ingest');
    }

    public function test_the_real_heavy_connectors_declare_the_heavy_queue_and_the_rest_the_default(): void
    {
        foreach ([WitsTradeConnector::class, GleifLeiCountsConnector::class, ManafethPartnersConnector::class] as $heavy) {
            $this->assertSame('ingest-heavy', (new $heavy)->queue(), $heavy);
        }
        $this->assertSame('ingest', (new ImfWeoConnector)->queue());
    }
}
