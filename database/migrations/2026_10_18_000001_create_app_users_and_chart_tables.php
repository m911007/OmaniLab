<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * مخطط app: حسابات المستخدمين وما يكتبونه (طلبات تحديث الرسوم، ملاحظات، شروحات معدَّلة).
 * هو المخطط الوحيد الذي يكتب فيه دور app_reader (بقية المخططات قراءة فقط له)، ولا علاقة له ببيانات المصادر.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE SCHEMA IF NOT EXISTS app;

        CREATE TABLE app.users (
            id             bigserial PRIMARY KEY,
            name           varchar(120) NOT NULL,
            email          varchar(190) NOT NULL UNIQUE,
            password       varchar(255) NOT NULL,
            is_admin       boolean NOT NULL DEFAULT false,
            remember_token varchar(100),
            created_at     timestamptz NOT NULL DEFAULT now(),
            updated_at     timestamptz NOT NULL DEFAULT now()
        );

        -- طلب تحديث رسم: ينفذه وكيل الذكاء الاصطناعي في فترة تشغيله ثم يسجّل رده.
        CREATE TABLE app.chart_requests (
            id             bigserial PRIMARY KEY,
            user_id        bigint NOT NULL REFERENCES app.users (id) ON DELETE CASCADE,
            chart_key      varchar(300) NOT NULL,
            chart_title    varchar(300) NOT NULL,
            page_path      varchar(200) NOT NULL,
            message        text NOT NULL,
            status         varchar(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'in_progress', 'done', 'rejected')),
            agent_response text,
            handled_at     timestamptz,
            created_at     timestamptz NOT NULL DEFAULT now(),
            updated_at     timestamptz NOT NULL DEFAULT now()
        );
        CREATE INDEX chart_requests_status_idx ON app.chart_requests (status, created_at);
        CREATE INDEX chart_requests_user_idx ON app.chart_requests (user_id, created_at DESC);

        CREATE TABLE app.chart_notes (
            id         bigserial PRIMARY KEY,
            user_id    bigint NOT NULL REFERENCES app.users (id) ON DELETE CASCADE,
            chart_key  varchar(300) NOT NULL,
            body       text NOT NULL,
            created_at timestamptz NOT NULL DEFAULT now(),
            updated_at timestamptz NOT NULL DEFAULT now()
        );
        CREATE INDEX chart_notes_chart_idx ON app.chart_notes (chart_key, created_at);

        -- الشرح المعدَّل يحل محل الشرح الافتراضي للرسم؛ وكل تعديل يُحفظ في السجل.
        CREATE TABLE app.chart_explanations (
            chart_key   varchar(300) PRIMARY KEY,
            explanation jsonb NOT NULL,
            edited_by   bigint REFERENCES app.users (id) ON DELETE SET NULL,
            updated_at  timestamptz NOT NULL DEFAULT now()
        );
        CREATE TABLE app.chart_explanation_revisions (
            id          bigserial PRIMARY KEY,
            chart_key   varchar(300) NOT NULL,
            explanation jsonb NOT NULL,
            edited_by   bigint REFERENCES app.users (id) ON DELETE SET NULL,
            source      varchar(20) NOT NULL DEFAULT 'user',
            created_at  timestamptz NOT NULL DEFAULT now()
        );
        CREATE INDEX chart_explanation_revisions_idx ON app.chart_explanation_revisions (chart_key, created_at DESC);

        GRANT USAGE ON SCHEMA app TO app_reader;
        GRANT SELECT, INSERT, UPDATE, DELETE ON app.users, app.chart_requests, app.chart_notes, app.chart_explanations TO app_reader;
        GRANT SELECT, INSERT ON app.chart_explanation_revisions TO app_reader;
        GRANT USAGE ON ALL SEQUENCES IN SCHEMA app TO app_reader;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP SCHEMA IF EXISTS app CASCADE');
    }
};
