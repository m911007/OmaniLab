<?php

namespace Tests\Feature;

use App\Core\Ingestion\AliasMerger;
use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\IngestRunner;
use App\Core\Ingestion\Promoter;
use App\Models\Dataset;
use Illuminate\Support\Facades\DB;
use Tests\DatabaseTestCase;
use Tests\Support\FakeConnector;

class AliasMergerTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['omanilab.connectors' => [FakeConnector::class], 'omanilab.entity_aliases' => []]);
        FakeConnector::reset();
        $this->artisan('datasets:sync')->assertSuccessful();

        // وضع قديم: الكيانان دخلا برمزين مختلفين قبل تعريف alias.
        FakeConnector::$custom = function () {
            yield new ParsedEntity('KOS', 'Kosovo', 'country', 'كوسوفو');
            yield new ParsedEntity('XKX', 'Kosovo', 'country');
            yield new ParsedRecord('fake.GDP', 'KOS', '2023', 1.0);
            yield new ParsedRecord('fake.GDP', 'KOS', '2024', 2.0);
            yield new ParsedRecord('fake.GDP', 'XKX', '2024', 2.5);
        };
        $run = app(IngestRunner::class)->run(Dataset::where('key', 'fake.test')->firstOrFail(), 'test');
        $this->assertSame('promoted', app(Promoter::class)->promote($run)->status);
        $kos = DB::table('core.entities')->where('code', 'KOS')->value('id');
        DB::table('core.entity_attributes')->insert(['entity_id' => $kos, 'key' => 'iso2', 'value' => json_encode('XK')]);
    }

    public function test_it_moves_the_aliased_entitys_data_to_the_canonical_code_and_keeps_the_canonical_value_on_conflict(): void
    {
        config(['omanilab.entity_aliases' => ['KOS' => 'XKX']]);

        $merged = app(AliasMerger::class)->merge();

        $this->assertSame(1, $merged);
        $this->assertSame(['XKX'], DB::table('core.entities')->pluck('code')->all());
        $xkx = DB::table('core.entities')->where('code', 'XKX')->first();
        $this->assertSame('كوسوفو', $xkx->name_ar, 'الاسم العربي ينتقل إن لم يكن للموحَّد اسم');
        $values = DB::table('core.observations')->where('entity_id', $xkx->id)->orderBy('period')->pluck('value', 'period')->map(fn ($v) => (float) $v)->all();
        $this->assertSame(['2023' => 1.0, '2024' => 2.5], $values);
        $this->assertSame('"XK"', DB::table('core.entity_attributes')->where('entity_id', $xkx->id)->value('value'));
    }

    public function test_it_is_safe_to_run_twice(): void
    {
        config(['omanilab.entity_aliases' => ['KOS' => 'XKX']]);

        app(AliasMerger::class)->merge();

        $this->assertSame(0, app(AliasMerger::class)->merge());
        $this->assertSame(2, DB::table('core.observations')->count());
    }
}
