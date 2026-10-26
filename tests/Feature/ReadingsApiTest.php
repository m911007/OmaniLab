<?php

namespace Tests\Feature;

use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\IngestRunner;
use App\Core\Ingestion\Promoter;
use App\Models\Dataset;
use Illuminate\Support\Facades\Cache;
use Tests\DatabaseTestCase;
use Tests\Support\FakeConnector;

class ReadingsApiTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['omanilab.connectors' => [FakeConnector::class]]);
        FakeConnector::reset();
        $this->artisan('datasets:sync')->assertSuccessful();

        FakeConnector::$custom = function () {
            foreach (['OMN' => 0.0, 'SAU' => 5.0, 'ARE' => 6.0, 'QAT' => -3.0] as $code => $offset) {
                yield new ParsedEntity($code, $code, 'country', $code);
                // عُمان تتحسن سنويًا حتى 2024
                foreach (range(2014, 2024) as $year) {
                    yield new ParsedRecord('fake.GDP', $code, (string) $year, ($year - 2010) + $offset);
                }
            }
        };
        $dataset = Dataset::where('key', 'fake.test')->firstOrFail();
        $run = app(IngestRunner::class)->run($dataset, 'test');
        $this->assertSame('promoted', app(Promoter::class)->promote($run)->status);

        config(['indicator_meta' => [
            'entity' => 'OMN', 'peers' => ['SAU', 'ARE', 'QAT'], 'history_years' => 10, 'pulse' => ['fake.GDP'],
            'indicators' => ['fake.GDP' => ['label' => 'مؤشر تجريبي', 'polarity' => 'higher', 'unit' => '%', 'digits' => 1]],
        ]]);
    }

    public function test_a_reading_has_a_verdict_references_and_a_generated_sentence(): void
    {
        $res = $this->getJson('/api/v1/readings?indicators=fake.GDP,nope.MISSING')->assertOk();

        $res->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.period', '2024')
            ->assertJsonPath('data.0.value', 14)
            ->assertJsonPath('data.0.previous.period', '2023')
            ->assertJsonPath('data.0.history.percentile', 100)
            ->assertJsonPath('data.0.gcc.rank', 3)
            ->assertJsonPath('data.0.gcc.of', 4)
            ->assertJsonPath('data.0.verdict.basis', ['history', 'gcc'])
            ->assertJsonPath('data.0.dataset.key', 'fake.test');
        $this->assertStringStartsWith('بلغ مؤشر تجريبي 14% في 2024، ارتفاعًا عن 13% في 2023. ترتيب عُمان 3 من 4 خليجيًا', $res->json('data.0.sentence'));
    }

    public function test_the_pulse_set_and_indicators_without_polarity_get_no_verdict(): void
    {
        config(['indicator_meta.indicators' => ['fake.GDP' => ['label' => 'مؤشر تجريبي', 'unit' => '%', 'digits' => 1]]]);

        $this->getJson('/api/v1/readings?set=pulse')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.verdict', null)
            ->assertJsonPath('data.0.history.percentile', null);
    }

    public function test_indicators_or_a_set_are_required(): void
    {
        $this->getJson('/api/v1/readings')->assertStatus(422);
    }
}
