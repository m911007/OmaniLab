<?php

namespace Tests\Feature;

use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Modules\Macro\Connectors\ImfAiReadinessConnector;
use App\Modules\Macro\Connectors\ImfDebtDatabaseConnector;
use App\Modules\Macro\Connectors\ImfFiscalMonitorConnector;
use App\Modules\Macro\Connectors\ImfReservesConnector;
use App\Modules\Macro\Connectors\ImfWeoConnector;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImfWeoConnectorTest extends TestCase
{
    private const BASE = 'https://www.imf.org/external/datamapper/api/v1';

    /** @return list<string> */
    private function codes(): array
    {
        return array_map(fn ($i) => substr($i->key, 4), (new ImfWeoConnector)->definition()->indicators);
    }

    /**
     * قائمة مؤشرات IMF كما يعيدها المصدر، مولّدة من تعريف الموصل (فتبقى متزامنة عند إضافة مؤشرات).
     *
     * @return array<string, mixed>
     */
    private function fixture(string $name): array
    {
        $meta = [];
        foreach ($this->codes() as $i => $code) {
            $meta[$code] = ['label' => $code, 'unit' => 'x', 'dataset' => 'WEO', 'source' => 'World Economic Outlook (October 2026)', 'last-modified' => sprintf('2026-10-08 14:43:%02d', 10 + $i)];
        }

        return ['indicators' => $meta, 'api' => ['version' => '1']];
    }

    /** @param  array<string, mixed>|null  $indicators */
    private function fakeImf(?array $indicators = null): void
    {
        $codes = $this->codes();
        $fakes = [
            self::BASE.'/indicators' => Http::response($indicators ?? $this->fixture('indicators')),
            self::BASE.'/countries' => Http::response(['countries' => ['OMN' => ['label' => 'Oman'], 'SAU' => ['label' => 'Saudi Arabia']]]),
            self::BASE.'/regions' => Http::response(['regions' => ['MECA' => ['label' => 'Middle East and Central Asia']]]),
            self::BASE.'/groups' => Http::response(['groups' => ['ADVEC' => ['label' => 'Advanced economies']]]),
        ];
        foreach ($codes as $code) {
            $fakes[self::BASE.'/'.$code] = Http::response(['values' => [$code => [
                'OMN' => ['2024' => 1.6, '2025' => 2.4, '2026' => null],
                'SAU' => ['2025' => 3.0],
            ]]]);
        }
        $this->freshHttp(); // Http::fake يراكم الردود والأول يغلب؛ نبدأ من جديد في كل استدعاء
        Http::fake($fakes);
    }

    public function test_probe_returns_release_version_and_latest_modification(): void
    {
        $this->fakeImf();

        $probe = (new ImfWeoConnector)->probe();

        $this->assertSame('World Economic Outlook (October 2026)', $probe->version);
        $this->assertSame(sprintf('2026-10-08 14:43:%02d', 10 + count($this->codes()) - 1), $probe->publishedAt->format('Y-m-d H:i:s'));
        $this->assertSame(40, strlen($probe->token));
    }

    public function test_probe_token_changes_when_any_indicator_is_modified(): void
    {
        $this->fakeImf();
        $before = (new ImfWeoConnector)->probe()->token;

        $changed = $this->fixture('indicators');
        $changed['indicators']['LUR']['last-modified'] = '2026-10-20 09:00:00';
        $this->fakeImf($changed);

        $this->assertNotSame($before, (new ImfWeoConnector)->probe()->token);
    }

    public function test_probe_fails_loudly_if_an_indicator_disappears(): void
    {
        $missing = $this->fixture('indicators');
        unset($missing['indicators']['LUR']);
        $this->fakeImf($missing);

        $this->expectExceptionMessage('LUR');
        (new ImfWeoConnector)->probe();
    }

    public function test_fetch_and_parse_produce_entities_and_records_skipping_nulls(): void
    {
        $this->fakeImf();
        $connector = new ImfWeoConnector;

        $items = iterator_to_array($connector->parse($connector->fetch()), false);
        $entities = array_filter($items, fn ($i) => $i instanceof ParsedEntity);
        $records = array_filter($items, fn ($i) => $i instanceof ParsedRecord);

        $this->assertEqualsCanonicalizing(['OMN', 'SAU', 'MECA', 'ADVEC'], array_map(fn ($e) => $e->code, $entities));
        $types = collect($entities)->mapWithKeys(fn ($e) => [$e->code => $e->type])->all();
        $this->assertSame(['OMN' => 'country', 'SAU' => 'country', 'MECA' => 'region', 'ADVEC' => 'group'], $types);

        $this->assertCount(count($this->codes()) * 3, $records, '3 نقاط صالحة لكل مؤشر، مع تجاهل null');
        $omn = collect($records)->first(fn ($r) => $r->indicatorKey === 'imf.NGDP_RPCH' && $r->entityCode === 'OMN' && $r->period === '2025');
        $this->assertSame(2.4, $omn->value);
    }

    public function test_requests_use_the_user_agent_accepted_by_the_imf_firewall(): void
    {
        $this->fakeImf();
        (new ImfWeoConnector)->probe();

        Http::assertSent(fn (Request $r) => str_starts_with($r->header('User-Agent')[0], 'curl/'));
    }

    public function test_definition_has_valid_unique_keys_and_rules_on_every_indicator(): void
    {
        $def = (new ImfWeoConnector)->definition();
        $keys = array_map(fn ($i) => $i->key, $def->indicators);

        $this->assertSame($keys, array_values(array_unique($keys)));
        foreach ($def->indicators as $i) {
            $this->assertStringStartsWith('imf.', $i->key);
            $this->assertNotEmpty($i->rules, "المؤشر {$i->key} بلا قواعد تحقق");
        }
    }

    public function test_every_imf_dataset_has_unique_keys_and_rules_and_never_claims_anothers_indicator(): void
    {
        $classes = [
            ImfWeoConnector::class,
            ImfFiscalMonitorConnector::class,
            ImfDebtDatabaseConnector::class,
            ImfReservesConnector::class,
            ImfAiReadinessConnector::class,
        ];

        $keys = [];
        foreach ($classes as $class) {
            $def = (new $class)->definition();
            $this->assertStringStartsWith('imf.', $def->key);
            foreach ($def->indicators as $i) {
                $keys[] = $i->key;
                $this->assertStringStartsWith('imf.', $i->key);
                $this->assertNotEmpty($i->rules, "{$i->key} بلا قواعد");
            }
        }

        $this->assertSame($keys, array_values(array_unique($keys)), 'مؤشر واحد لا يتبع مجموعتين');
        $this->assertGreaterThan(35, count($keys));
    }

    public function test_the_four_imf_datasets_share_one_source_record(): void
    {
        $sources = array_map(fn ($c) => (new $c)->definition()->source->key, [
            ImfWeoConnector::class,
            ImfFiscalMonitorConnector::class,
        ]);

        $this->assertSame(['imf', 'imf'], $sources);
    }
}
