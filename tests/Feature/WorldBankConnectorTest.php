<?php

namespace Tests\Feature;

use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Modules\WorldBank\Connectors\WorldBankConnector;
use App\Modules\WorldBank\Connectors\WorldBankMacroConnector;
use App\Modules\WorldBank\Connectors\WorldBankWealthConnector;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class WorldBankConnectorTest extends TestCase
{
    private const API = 'https://api.worldbank.org/v2';

    /** @return array<string, mixed> */
    private function row(string $iso3, string $countryId, string $year, ?float $value): array
    {
        return ['countryiso3code' => $iso3, 'country' => ['id' => $countryId, 'value' => 'x'], 'date' => $year, 'value' => $value];
    }

    /** @param  list<array<string, mixed>>|null  $rows  صفوف رد المؤشر (تتكرر لكل مؤشر) */
    private function fakeWb(?array $rows = null): void
    {
        $countries = [['id' => 'OMN', 'name' => 'Oman', 'region' => ['value' => 'Middle East']], ['id' => 'ARB', 'name' => 'Arab World', 'region' => ['value' => 'Aggregates']]];
        $rows ??= [
            $this->row('OMN', 'OM', '2024', 106.2), $this->row('OMN', 'OM', '2023', null),
            $this->row('ARB', '1A', '2024', 3500.0), $this->row('', '', '2024', 5.0),
        ];

        $this->freshHttp();
        Http::fake([
            self::API.'/country/OMN/indicator/*' => Http::response([['page' => 1, 'pages' => 1, 'per_page' => 1, 'total' => 1, 'lastupdated' => '2026-10-08'], [[]]]),
            self::API.'/country?*' => Http::response([['page' => 1, 'pages' => 1], $countries]),
            self::API.'/country/all/indicator/*' => Http::response([['page' => 1, 'pages' => 1], $rows]),
        ]);
    }

    public function test_purchasing_power_derives_the_price_level_and_leaves_the_exchange_rate_to_its_own_dataset(): void
    {
        $this->freshHttp();
        $row = fn (string $v, string $y) => ['countryiso3code' => 'OMN', 'country' => ['id' => 'OM', 'value' => 'x'], 'date' => $y, 'value' => (float) $v];
        Http::fake([
            self::API.'/country?*' => Http::response([['page' => 1, 'pages' => 1], [['id' => 'OMN', 'name' => 'Oman', 'region' => ['value' => 'Middle East']]]]),
            self::API.'/country/all/indicator/PA.NUS.PRVT.PP*' => Http::response([['page' => 1, 'pages' => 1], [$row('0.2', '2024')]]),
            self::API.'/country/all/indicator/PA.NUS.FCRF*' => Http::response([['page' => 1, 'pages' => 1], [$row('0.4', '2024')]]),
            self::API.'/country/all/indicator/*' => Http::response([['page' => 1, 'pages' => 1], []]),
        ]);
        $c = new \App\Modules\WorldBank\Connectors\WorldBankPurchasingPowerConnector;

        $keys = [];
        foreach ($c->parse($c->fetch()) as $item) {
            if ($item instanceof \App\Core\Ingestion\Data\ParsedRecord) {
                $keys[] = $item->indicatorKey.'|'.$item->period.'|'.$item->value;
            }
        }

        $this->assertEqualsCanonicalizing(['wb.PA.NUS.PRVT.PP|2024|0.2', 'wb.PRICE_LEVEL_PRIVATE|2024|50'], $keys);
    }

    public function test_probe_uses_the_source_lastupdated_date(): void
    {
        $this->fakeWb();

        $p = (new WorldBankMacroConnector)->probe();

        $this->assertSame('2026-10-08|NY.GDP.MKTP.CD', $p->token);
        $this->assertSame('2026-10-08', $p->publishedAt->toDateString());
        $this->assertStringContainsString('World Development Indicators', $p->version);
    }

    public function test_probe_fails_loudly_without_lastupdated(): void
    {
        Http::fake([self::API.'/*' => Http::response([['page' => 1], []])]);

        $this->expectException(RuntimeException::class);
        (new WorldBankMacroConnector)->probe();
    }

    public function test_parse_types_entities_skips_nulls_and_uncoded_territories(): void
    {
        $this->fakeWb();
        $c = new WorldBankMacroConnector;

        $items = iterator_to_array($c->parse($c->fetch()), false);
        $types = collect($items)->filter(fn ($i) => $i instanceof ParsedEntity)->mapWithKeys(fn ($e) => [$e->code => $e->type])->all();
        $records = collect($items)->filter(fn ($i) => $i instanceof ParsedRecord && $i->indicatorKey === 'wb.NY.GDP.MKTP.CD')->map(fn ($r) => "{$r->entityCode}|{$r->period}|{$r->value}")->values()->all();

        $this->assertSame(['OMN' => 'country', 'ARB' => 'group'], $types);
        $this->assertSame(['OMN|2024|106.2', 'ARB|2024|3500'], $records, 'القيمة الفارغة وغير المرمَّز يُتخطيان');
    }

    public function test_follows_pagination_for_large_indicators(): void
    {
        $this->freshHttp();
        Http::fake([
            self::API.'/country?*' => Http::response([['page' => 1, 'pages' => 1], []]),
            self::API.'/country/all/indicator/*page=1*' => Http::response([['page' => 1, 'pages' => 2], [$this->row('OMN', 'OM', '2023', 1.0)]]),
            self::API.'/country/all/indicator/*page=2*' => Http::response([['page' => 2, 'pages' => 2], [$this->row('OMN', 'OM', '2024', 2.0)]]),
        ]);

        $c = new WorldBankMacroConnector;
        $records = collect(iterator_to_array($c->parse($c->fetch()), false))
            ->filter(fn ($i) => $i instanceof ParsedRecord && $i->indicatorKey === 'wb.NY.GDP.MKTP.CD')->map(fn ($r) => $r->period)->all();

        $this->assertSame(['2023', '2024'], $records);
    }

    public function test_an_api_error_message_aborts_the_run_with_its_text(): void
    {
        Http::fake([
            self::API.'/country?*' => Http::response([['page' => 1, 'pages' => 1], []]),
            self::API.'/country/all/indicator/*' => Http::response([['message' => [['id' => '120', 'key' => 'Invalid value', 'value' => 'The provided parameter value is not valid']]]]),
        ]);

        $this->expectExceptionMessage('The provided parameter value is not valid');
        (new WorldBankMacroConnector)->fetch();
    }

    public function test_definition_prefixes_indicators_and_every_one_has_rules(): void
    {
        $def = (new WorldBankMacroConnector)->definition();

        foreach ($def->indicators as $i) {
            $this->assertStringStartsWith('wb.', $i->key);
            $this->assertNotEmpty($i->rules);
        }
        $this->assertSame('worldbank.macro', $def->key);
    }

    public function test_every_registered_world_bank_connector_has_unique_prefixed_indicators_with_rules(): void
    {
        $seen = [];
        foreach (config('omanilab.connectors') as $class) {
            if (! is_subclass_of($class, WorldBankConnector::class)) {
                continue;
            }
            foreach ((new $class)->definition()->indicators as $i) {
                $this->assertMatchesRegularExpression('/^wbp?\./', $i->key);
                $this->assertNotEmpty($i->rules, $i->key);
                $this->assertArrayNotHasKey($i->key, $seen, "{$i->key} معرَّف في أكثر من مجموعة");
                $seen[$i->key] = true;
            }
        }
        $this->assertGreaterThan(100, count($seen));
    }

    public function test_the_wealth_accounts_connector_queries_its_own_world_bank_source(): void
    {
        $this->freshHttp();
        $urls = [];
        Http::fake(function (Request $r) use (&$urls) {
            $urls[] = $r->url();

            return Http::response([['page' => 1, 'pages' => 1, 'lastupdated' => '2026-09-01'], []]);
        });

        (new WorldBankWealthConnector)->probe();

        $this->assertStringContainsString('source=59', $urls[0]);
    }
}
