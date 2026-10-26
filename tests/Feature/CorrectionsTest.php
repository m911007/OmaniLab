<?php

namespace Tests\Feature;

use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\IngestRunner;
use App\Core\Ingestion\Promoter;
use App\Core\Quality\Exclusions;
use App\Core\Quality\UnitShift;
use App\Models\Dataset;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\DatabaseTestCase;
use Tests\Support\FakeConnector;

class CorrectionsTest extends DatabaseTestCase
{
    public function test_the_audit_flags_unit_breaks_and_the_api_lists_corrections(): void
    {
        Cache::flush();
        config(['omanilab.connectors' => [FakeConnector::class]]);
        FakeConnector::reset();
        $this->artisan('datasets:sync')->assertSuccessful();
        FakeConnector::$custom = function () {
            yield new ParsedEntity('OMN', 'Oman', 'country', 'عُمان');
            yield new ParsedRecord('fake.GDP', 'OMN', '2018', 40.0);
        };
        $run = app(IngestRunner::class)->run(Dataset::where('key', 'fake.test')->firstOrFail(), 'test');
        app(Promoter::class)->promote($run);

        // بيانات قديمة نُشرت قبل قواعد التدقيق: 40 ثم 0.04 (انقطاع ×1000) — تُدرج مباشرة
        $indicator = DB::table('core.indicators')->where('key', 'fake.GDP')->value('id');
        $entity = DB::table('core.entities')->where('code', 'OMN')->value('id');
        foreach (['2019' => 41.0, '2020' => 39.0, '2021' => 42.0, '2022' => 0.04, '2023' => 0.041] as $period => $value) {
            DB::table('core.observations')->insert(['indicator_id' => $indicator, 'entity_id' => $entity, 'period' => $period, 'value' => $value, 'run_id' => $run->id]);
        }

        $this->artisan('data:audit');
        $this->assertSame('suspected', DB::table('ops.corrections')->where('indicator_key', 'fake.GDP')->where('period', '2022')->value('status'));

        UnitShift::fix(['2018' => 40.0, '2019' => 41.0, '2020' => 39.0, '2021' => 42.0, '2022' => 0.04], 'fake.GDP', 'OMN');
        $this->assertSame('applied', DB::table('ops.corrections')->where('indicator_key', 'fake.GDP')->where('period', '2022')->value('status'));

        $this->getJson('/api/v1/corrections')->assertOk()
            ->assertJsonPath('data.0.indicator_key', 'fake.GDP')
            ->assertJsonPath('data.0.name_ar', 'نمو');
    }

    public function test_source_errors_are_excluded_before_publishing_and_reviewed_points_are_dismissed(): void
    {
        Cache::flush();
        config(['omanilab.connectors' => [FakeConnector::class]]);
        FakeConnector::reset();
        $this->artisan('datasets:sync')->assertSuccessful();
        config(['data_exclusions' => [
            'exclude' => [['indicator' => 'fake.GDP', 'entity' => 'OMN', 'period' => '2022', 'reason' => 'خطأ في المصدر.']],
            'reviewed' => [['indicator' => 'fake.GDP', 'entity' => 'OMN', 'periods' => ['2020'], 'reason' => 'ليس خطأ.']],
        ]]);
        FakeConnector::$custom = function () {
            yield new ParsedEntity('OMN', 'Oman', 'country', 'عُمان');
            foreach (['2020' => 3.0, '2021' => 3.1, '2022' => 30.0, '2023' => 3.2] as $period => $value) {
                yield new ParsedRecord('fake.GDP', 'OMN', $period, $value);
            }
        };
        $run = app(IngestRunner::class)->run(Dataset::where('key', 'fake.test')->firstOrFail(), 'test');
        app(Promoter::class)->promote($run);

        $this->assertSame(['2020', '2021', '2023'], DB::table('core.observations as o')->join('core.indicators as i', 'i.id', '=', 'o.indicator_id')->where('i.key', 'fake.GDP')->orderBy('o.period')->pluck('o.period')->all());
        $row = DB::table('ops.corrections')->where('indicator_key', 'fake.GDP')->where('period', '2022')->first();
        $this->assertSame('excluded', $row->status);
        $this->assertSame(30.0, (float) $row->original);

        DB::table('ops.corrections')->insert(['indicator_key' => 'fake.GDP', 'entity_code' => 'OMN', 'period' => '2020', 'kind' => UnitShift::KIND, 'status' => 'suspected', 'original' => 3.0, 'note' => 'x']);
        app(Exclusions::class)->markReviewed();
        $this->assertSame('dismissed', DB::table('ops.corrections')->where('indicator_key', 'fake.GDP')->where('period', '2020')->value('status'));
    }
}
