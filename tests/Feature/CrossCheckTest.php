<?php

namespace Tests\Feature;

use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\IngestRunner;
use App\Core\Ingestion\Promoter;
use App\Models\Dataset;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\DatabaseTestCase;
use Tests\Support\FakeConnector;

class CrossCheckTest extends DatabaseTestCase
{
    private function seedSources(float $second2024, float $second2023 = 10.0): void
    {
        Cache::flush();
        config(['omanilab.connectors' => [FakeConnector::class]]);
        FakeConnector::reset();
        $this->artisan('datasets:sync')->assertSuccessful();
        FakeConnector::$custom = function () {
            yield new ParsedEntity('OMN', 'Oman', 'country');
            yield new ParsedRecord('fake.GDP', 'OMN', '2023', 10.0);
            yield new ParsedRecord('fake.GDP', 'OMN', '2024', 12.0);
        };
        $run = app(IngestRunner::class)->run(Dataset::where('key', 'fake.test')->firstOrFail(), 'test');
        $this->assertSame('promoted', app(Promoter::class)->promote($run)->status);

        // مصدر ثانٍ بوحدة مختلفة (آلاف) يقيس الشيء نفسه.
        $dataset = DB::table('ops.datasets')->first();
        DB::table('core.indicators')->insert(['key' => 'second.GDP', 'dataset_id' => $dataset->id, 'name_ar' => 'ناتج (مصدر ثانٍ)', 'name_en' => 'GDP 2', 'unit' => 'ألف', 'frequency' => 'A']);
        $indicator = DB::table('core.indicators')->where('key', 'second.GDP')->value('id');
        $entity = DB::table('core.entities')->where('code', 'OMN')->value('id');
        foreach (['2023' => $second2023 * 1000, '2024' => $second2024 * 1000] as $period => $v) {
            DB::table('core.observations')->insert(['indicator_id' => $indicator, 'entity_id' => $entity, 'period' => $period, 'value' => $v, 'run_id' => $run->id]);
        }
        config(['crosschecks' => ['compare_periods' => 3, 'groups' => [['key' => 'g', 'label' => 'ناتج', 'mode' => 'relative', 'tolerance' => 0.03, 'members' => [
            ['indicator' => 'fake.GDP', 'scale' => 1], ['indicator' => 'second.GDP', 'scale' => 0.001],
        ]]]]]);
    }

    public function test_sources_that_agree_after_unit_conversion_are_marked_as_agreeing(): void
    {
        $this->seedSources(12.1);

        $this->getJson('/api/v1/insights/crosschecks?entity=OMN')->assertOk()->assertJsonPath('data.0.verdict', 'agree')->assertJsonPath('data.0.indicators', ['fake.GDP', 'second.GDP']);
    }

    public function test_sources_that_diverge_are_marked_as_differing_and_the_detail_converts_every_series_to_the_requested_unit(): void
    {
        $this->seedSources(15.0);

        $this->getJson('/api/v1/insights/crosschecks?entity=OMN')->assertJsonPath('data.0.verdict', 'differ');
        $detail = $this->getJson('/api/v1/insights/crosschecks/detail?indicator=fake.GDP&entity=OMN')->assertOk();
        $detail->assertJsonPath('verdict', 'differ')->assertJsonCount(2, 'sources')->assertJsonPath('periods.0.period', '2024')->assertJsonPath('periods.0.agree', false);
        $second = array_values(array_filter((array) $detail->json('sources'), fn (array $s) => $s['indicator'] === 'second.GDP'))[0];
        $this->assertEqualsWithDelta(15.0, $second['points'][1][1], 1e-9, 'نفس وحدة المؤشر الأساسي');
    }

    public function test_the_detail_for_an_indicator_in_no_group_is_not_found(): void
    {
        $this->seedSources(12.0);

        $this->getJson('/api/v1/insights/crosschecks/detail?indicator=nope.X')->assertNotFound();
    }

    public function test_a_group_with_data_from_one_source_only_is_single(): void
    {
        $this->seedSources(12.0);
        DB::table('core.observations')->whereIn('indicator_id', DB::table('core.indicators')->where('key', 'second.GDP')->pluck('id'))->delete();

        $this->getJson('/api/v1/insights/crosschecks?entity=OMN')->assertJsonPath('data.0.verdict', 'single');
    }
}
