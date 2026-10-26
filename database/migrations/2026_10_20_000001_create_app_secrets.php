<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * مفاتيح الخدمات الخارجية (Anthropic وSerpAPI) يضعها المشرف من الواجهة بعد تسجيل الدخول بدل ملف .env.
 * القيمة مشفَّرة بـAPP_KEY (Crypt) ولا تُعاد للمتصفح أبدًا. عامل ذكاء الأسواق (ingest_writer) يقرأها فقط لينفّذ الجلب.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE TABLE app.secrets (
            name       varchar(60) PRIMARY KEY,
            value      text NOT NULL,
            hint       varchar(12),
            updated_by bigint REFERENCES app.users (id) ON DELETE SET NULL,
            updated_at timestamptz NOT NULL DEFAULT now()
        );
        GRANT SELECT, INSERT, UPDATE, DELETE ON app.secrets TO app_reader;
        GRANT USAGE ON SCHEMA app TO ingest_writer;
        GRANT SELECT ON app.secrets TO ingest_writer;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS app.secrets');
    }
};
