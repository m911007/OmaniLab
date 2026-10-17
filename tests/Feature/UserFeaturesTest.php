<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\DatabaseTestCase;

class UserFeaturesTest extends DatabaseTestCase
{
    private const EXPLANATION = [
        'meaning' => 'هذا القياس يوضح كذا وكذا بلغة بسيطة.',
        'shows' => 'الرسم يعرض تطور القيمة عبر السنوات.',
        'read' => ['الصعود يعني تحسنًا'],
        'why' => 'لأنه يهم صانع القرار',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        DB::unprepared('TRUNCATE app.chart_explanation_revisions, app.chart_explanations, app.chart_notes, app.chart_requests, app.users RESTART IDENTITY CASCADE');
        RateLimiter::clear('a@example.com|127.0.0.1');
    }

    private function user(bool $admin = false, string $email = 'a@example.com'): User
    {
        return User::create(['name' => 'مستخدم', 'email' => $email, 'password' => 'correct-horse-battery', 'is_admin' => $admin]);
    }

    public function test_login_logout_and_me(): void
    {
        $this->user();
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJson(['user' => null]);
        $this->postJson('/api/v1/auth/login', ['email' => 'a@example.com', 'password' => 'wrong'])->assertStatus(422);
        $this->postJson('/api/v1/auth/login', ['email' => 'a@example.com', 'password' => 'correct-horse-battery'])->assertOk()->assertJsonPath('user.is_admin', false);
        $this->getJson('/api/v1/auth/me')->assertJsonPath('user.email', 'a@example.com');
        $this->postJson('/api/v1/auth/logout')->assertOk();
        $this->getJson('/api/v1/auth/me')->assertJson(['user' => null]);
    }

    public function test_login_is_throttled_after_five_failures(): void
    {
        $this->user();
        foreach (range(1, 5) as $_) {
            $this->postJson('/api/v1/auth/login', ['email' => 'a@example.com', 'password' => 'bad'])->assertStatus(422);
        }
        $this->postJson('/api/v1/auth/login', ['email' => 'a@example.com', 'password' => 'correct-horse-battery'])->assertStatus(429);
    }

    public function test_writes_require_login(): void
    {
        $this->postJson('/api/v1/chart-requests', [])->assertUnauthorized();
        $this->getJson('/api/v1/chart-notes?chart_key=x')->assertUnauthorized();
        $this->putJson('/api/v1/chart-explanations', [])->assertUnauthorized();
    }

    public function test_requests_are_private_to_their_owner_except_for_admins_who_can_set_status(): void
    {
        $alice = $this->user(false, 'alice@example.com');
        $bob = $this->user(false, 'bob@example.com');
        $admin = $this->user(true, 'admin@example.com');
        $payload = ['chart_key' => '/oman::التضخم', 'chart_title' => 'التضخم', 'page_path' => '/oman', 'message' => 'أضيفوا بيانات التضخم الشهرية'];

        $id = $this->actingAs($alice)->postJson('/api/v1/chart-requests', $payload)->assertCreated()->json('data.id');
        $this->actingAs($bob)->getJson('/api/v1/chart-requests')->assertJsonCount(0, 'data');
        $this->actingAs($alice)->getJson('/api/v1/chart-requests')->assertJsonCount(1, 'data');
        $this->actingAs($alice)->patchJson("/api/v1/chart-requests/{$id}", ['status' => 'done'])->assertForbidden();
        $this->actingAs($admin)->getJson('/api/v1/chart-requests')->assertJsonCount(1, 'data');
        $this->actingAs($admin)->patchJson("/api/v1/chart-requests/{$id}", ['status' => 'rejected', 'agent_response' => 'خارج النطاق'])->assertOk()->assertJsonPath('data.status', 'rejected');
        unset($bob);
    }

    public function test_notes_can_only_be_deleted_by_author_or_admin(): void
    {
        $alice = $this->user(false, 'alice@example.com');
        $bob = $this->user(false, 'bob@example.com');
        $id = $this->actingAs($alice)->postJson('/api/v1/chart-notes', ['chart_key' => 'k', 'body' => 'ملاحظة مهمة'])->assertCreated()->json('data.id');
        $this->actingAs($bob)->getJson('/api/v1/chart-notes?chart_key=k')->assertJsonCount(1, 'data');
        $this->actingAs($bob)->deleteJson("/api/v1/chart-notes/{$id}")->assertForbidden();
        $this->actingAs($alice)->deleteJson("/api/v1/chart-notes/{$id}")->assertOk();
    }

    public function test_edited_explanations_are_public_validated_and_versioned(): void
    {
        $user = $this->user();
        $this->actingAs($user)->putJson('/api/v1/chart-explanations', ['chart_key' => 'k'] + ['meaning' => 'قصير'])->assertUnprocessable();
        $this->actingAs($user)->putJson('/api/v1/chart-explanations', ['chart_key' => 'k'] + self::EXPLANATION)->assertOk();
        $this->actingAs($user)->putJson('/api/v1/chart-explanations', ['chart_key' => 'k'] + ['why' => 'نسخة ثانية للشرح'] + self::EXPLANATION)->assertOk();

        auth()->logout();
        $this->getJson('/api/v1/chart-explanations')->assertOk()->assertJsonPath('data.k.why', 'نسخة ثانية للشرح');
        $this->assertSame(2, DB::table('app.chart_explanation_revisions')->where('chart_key', 'k')->count());
    }

    public function test_agent_api_requires_its_token_and_can_fulfil_requests(): void
    {
        $this->getJson('/api/agent/requests')->assertStatus(503);

        config(['omanilab.agent_token' => 'secret-agent-token']);
        $this->getJson('/api/agent/requests')->assertUnauthorized();
        $this->getJson('/api/agent/requests', ['Authorization' => 'Bearer wrong'])->assertUnauthorized();

        $user = $this->user();
        $id = $this->actingAs($user)->postJson('/api/v1/chart-requests', ['chart_key' => 'k', 'chart_title' => 't', 'page_path' => '/p', 'message' => 'حدّثوا هذا الرسم'])->json('data.id');
        auth()->logout();
        $h = ['Authorization' => 'Bearer secret-agent-token'];

        $this->getJson('/api/agent/requests', $h)->assertOk()->assertJsonCount(1, 'data');
        $this->patchJson("/api/agent/requests/{$id}", ['status' => 'done', 'response' => 'أُضيف المؤشر'], $h)->assertOk()->assertJsonPath('data.status', 'done');
        $this->getJson('/api/agent/requests', $h)->assertJsonCount(0, 'data');
        $this->putJson('/api/agent/explanations', ['chart_key' => 'k'] + self::EXPLANATION, $h)->assertOk();
        $this->assertSame('agent', DB::table('app.chart_explanation_revisions')->value('source'));
    }

    public function test_users_create_command_makes_admins_and_resets_passwords(): void
    {
        $this->artisan('users:create', ['email' => 'Boss@Example.com', '--admin' => true, '--password' => 'long-enough-password'])->assertSuccessful();
        $u = User::where('email', 'boss@example.com')->firstOrFail();
        $this->assertTrue($u->is_admin);
        $this->artisan('users:create', ['email' => 'boss@example.com', '--password' => 'short'])->assertFailed();
    }
}
