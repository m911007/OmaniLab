<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** لقطات نتائج المقارنة المعيارية لأسعار الجهة (قابلية التدقيق: نفس المدخلات تعطي نفس النتيجة، والبيانات المستخدمة محفوظة وقت الحساب). */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE TABLE intel.benchmark_results (
            id              bigserial PRIMARY KEY,
            organization_id bigint NOT NULL REFERENCES app.organizations (id) ON DELETE CASCADE,
            price_id        bigint NOT NULL REFERENCES app.supplier_prices (id) ON DELETE CASCADE,
            result_hash     varchar(64) NOT NULL,
            result          jsonb NOT NULL,
            computed_by     bigint REFERENCES app.users (id) ON DELETE SET NULL,
            computed_at     timestamptz NOT NULL DEFAULT now(),
            UNIQUE (price_id, result_hash)
        );
        GRANT SELECT, INSERT ON intel.benchmark_results TO app_reader;
        GRANT USAGE ON ALL SEQUENCES IN SCHEMA intel TO app_reader;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS intel.benchmark_results');
    }
};
