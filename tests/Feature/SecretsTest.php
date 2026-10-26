<?php

namespace Tests\Feature;

use App\Core\Notifications\TelegramClient;
use App\Core\Support\ApiKeys;
use App\Core\Support\Secrets;
use App\Models\User;
use App\Modules\MarketIntel\ProductSearch;
use Illuminate\Support\Facades\Http;
use Tests\DatabaseTestCase;

class SecretsTest extends DatabaseTestCase
{
    private function user(bool $admin): User
    {
        return User::create(['name' => 'م', 'email' => uniqid('s').'@example.com', 'password' => 'correct-horse-battery', 'is_admin' => $admin]);
    }

    protected function tearDown(): void
    {
        foreach (array_keys(Secrets::NAMES) as $name) {
            Secrets::forget($name);
        }
        parent::tearDown();
    }

    public function test_secret_is_encrypted_at_rest_and_overrides_env(): void
    {
        config(['omanilab.prices.serpapi_key' => 'env-key-1234567890']);
        Secrets::put('serpapi_key', 'db-key-abcdefghijkl', null);

        $raw = (string) \DB::table('app.secrets')->where('name', 'serpapi_key')->value('value');
        $this->assertStringNotContainsString('db-key', $raw);
        $this->assertSame('db-key-abcdefghijkl', Secrets::get('serpapi_key'));

        Secrets::forget('serpapi_key');
        $this->assertSame('env-key-1234567890', Secrets::get('serpapi_key'), 'احتياطي .env عند غياب المخزَّن');
    }

    public function test_only_admin_can_manage_secrets_and_value_is_never_returned(): void
    {
        $this->actingAs($this->user(false))->getJson('/api/v1/settings/secrets')->assertForbidden();
        $this->actingAs($this->user(false))->putJson('/api/v1/settings/secrets/serpapi_key', ['value' => 'abcdefghijklmnop1234'])->assertForbidden();

        $admin = $this->user(true);
        $res = $this->actingAs($admin)->putJson('/api/v1/settings/secrets/serpapi_key', ['value' => 'abcdefghijklmnop1234'])->assertOk();
        $this->assertStringNotContainsString('abcdefghijklmnop', $res->getContent());
        $row = collect($res->json('data'))->firstWhere('name', 'serpapi_key');
        $this->assertTrue($row['configured']);
        $this->assertSame('db', $row['source']);
        $this->assertSame('1234', $row['hint']);

        $this->actingAs($admin)->putJson('/api/v1/settings/secrets/serpapi_key', ['value' => 'short'])->assertStatus(422);
        $this->actingAs($admin)->putJson('/api/v1/settings/secrets/unknown', ['value' => 'abcdefghijklmnop1234'])->assertNotFound();
        $this->actingAs($admin)->deleteJson('/api/v1/settings/secrets/serpapi_key')->assertOk();
        $this->assertFalse(Secrets::has('serpapi_key'));
    }

    public function test_capabilities_reflect_stored_key(): void
    {
        $this->getJson('/api/v1/market-intel/capabilities')->assertJsonPath('shopping', false);
        Secrets::put('serpapi_key', 'abcdefghijklmnop1234', null);
        $this->getJson('/api/v1/market-intel/capabilities')->assertJsonPath('shopping', true);
    }

    public function test_product_search_understands_arabic_and_multiword(): void
    {
        $products = [
            ['id' => 52709, 'hs4' => '2709', 'name_en' => 'Crude Petroleum', 'name_ar' => 'وقود معدني وزيوت (نفط وغاز) — Crude Petroleum'],
            ['id' => 52710, 'hs4' => '2710', 'name_en' => 'Refined Petroleum', 'name_ar' => 'وقود معدني وزيوت (نفط وغاز) — Refined Petroleum'],
            ['id' => 178703, 'hs4' => '8703', 'name_en' => 'Cars', 'name_ar' => 'مركبات برية — Cars'],
            ['id' => 11001, 'hs4' => '1001', 'name_en' => 'Wheat', 'name_ar' => 'حبوب — Wheat'],
        ];
        $s = new ProductSearch;

        $this->assertSame('8703', $s->search($products, 'السيارات')[0]['hs4']);
        $this->assertSame('1001', $s->search($products, 'قمح')[0]['hs4']);
        $this->assertSame('2709', $s->search($products, 'نفط خام')[0]['hs4'], 'كلمتان: النفط والخام معًا');
        $this->assertSame(['2709', '2710'], array_column($s->search($products, 'petroleum'), 'hs4'));
        $this->assertSame('2710', $s->search($products, '2710')[0]['hs4']);
        $this->assertSame([], $s->search($products, 'كمبيوتر محمول'));
    }

    public function test_every_key_has_its_own_format_and_groups_for_the_page(): void
    {
        $admin = $this->user(true);
        $put = fn (string $name, string $value) => $this->actingAs($admin)->putJson("/api/v1/settings/secrets/{$name}", ['value' => $value]);

        // BLS: 32 خانة سداسية عشرية فقط
        $put('bls_api_key', 'not-a-bls-key')->assertStatus(422)->assertJsonValidationErrors('value');
        $put('bls_api_key', str_repeat('a1', 16))->assertOk();
        // تيليغرام: رمز البوت برقم وشرطة نقطتين، ومعرّف المحادثة رقم أو @قناة
        $put('telegram_bot_token', 'abcdefghijklmnopqrstuvwx')->assertStatus(422);
        $put('telegram_bot_token', '123456789:AAFabcdefghijklmnopqrstuvwxyz012345')->assertOk();
        $put('telegram_chat_id', 'abc')->assertStatus(422);
        $res = $put('telegram_chat_id', '-1001234567890')->assertOk();

        $rows = collect($res->json('data'));
        $this->assertEqualsCanonicalizing(['ذكاء الأسواق', 'التنبيهات', 'مصادر البيانات'], $rows->pluck('group')->unique()->values()->all());
        // النوع text (معرّف المحادثة) يُعاد كما هو، أما الأسرار فلا تُعاد قيمتها أبدًا بل آخر 4 أحرف
        $this->assertSame('-1001234567890', $rows->firstWhere('name', 'telegram_chat_id')['value']);
        $secret = $rows->firstWhere('name', 'telegram_bot_token');
        $this->assertNull($secret['value']);
        $this->assertSame('2345', $secret['hint']);
        $this->assertStringNotContainsString('AAFabcdefghijkl', $res->getContent());
        $this->assertNotNull($rows->firstWhere('name', 'comtrade_api_key')['url']);
    }

    public function test_telegram_client_reads_the_stored_token_and_chat(): void
    {
        $client = new TelegramClient;
        $this->assertFalse($client->isConfigured());

        Secrets::put('telegram_bot_token', '123456789:AAFabcdefghijklmnopqrstuvwxyz012345', null);
        Secrets::put('telegram_chat_id', '-1001234567890', null);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->assertTrue($client->isConfigured());
        $client->send('اختبار');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'bot123456789:AAFabcdefghijklmnopqrstuvwxyz012345/sendMessage') && $r['chat_id'] === '-1001234567890');
    }

    public function test_source_keys_switch_bls_and_comtrade_to_the_higher_limit_endpoints(): void
    {
        config(['omanilab.keys.bls' => null, 'omanilab.keys.comtrade' => null]);
        $this->assertStringContainsString('/publicAPI/v1/', ApiKeys::blsUrl());
        $this->assertSame(['seriesid' => ['A']], ApiKeys::blsBody(['seriesid' => ['A']]));
        $preview = 'https://comtradeapi.un.org/public/v1/preview/C/M/HS';
        $this->assertSame($preview, ApiKeys::comtradeUrl($preview));
        $this->assertSame([], ApiKeys::comtradeHeaders());

        Secrets::put('bls_api_key', str_repeat('ab', 16), null);
        Secrets::put('comtrade_api_key', str_repeat('Cd', 16), null);

        $this->assertStringContainsString('/publicAPI/v2/', ApiKeys::blsUrl());
        $this->assertSame(str_repeat('ab', 16), ApiKeys::blsBody(['seriesid' => ['A']])['registrationkey']);
        $this->assertSame('https://comtradeapi.un.org/data/v1/get/C/M/HS', ApiKeys::comtradeUrl($preview));
        $this->assertSame(['Ocp-Apim-Subscription-Key' => str_repeat('Cd', 16)], ApiKeys::comtradeHeaders());
    }
}
