<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * سلاسل أسعار يرفعها المحلل بملف Excel/CSV دوري (مثل مؤشرات مواد البناء من الهيئة العامة للإحصاء السعودية GASTAT أو أي جهة بلا واجهة برمجية).
 * لكل نقطة أصلها (الملف والرافع والوقت)، والسلسلة تُربط بعقد السلاسل كأي سلسلة أخرى بمفتاح upload.<slug>.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE TABLE intel.uploads (
            id          bigserial PRIMARY KEY,
            source_name varchar(160) NOT NULL,
            source_url  varchar(600),
            file_name   varchar(255) NOT NULL,
            rows        integer NOT NULL DEFAULT 0,
            notes       text,
            uploaded_by bigint REFERENCES app.users (id) ON DELETE SET NULL,
            created_at  timestamptz NOT NULL DEFAULT now()
        );
        CREATE TABLE intel.uploaded_series (
            id          bigserial PRIMARY KEY,
            key         varchar(128) NOT NULL UNIQUE,
            label_ar    varchar(200) NOT NULL,
            source_name varchar(160) NOT NULL,
            entity_code varchar(32) NOT NULL,
            unit        varchar(40),
            currency    varchar(3),
            frequency   varchar(1) NOT NULL CHECK (frequency IN ('D', 'W', 'M', 'Q', 'A')),
            created_at  timestamptz NOT NULL DEFAULT now(),
            updated_at  timestamptz NOT NULL DEFAULT now()
        );
        CREATE TABLE intel.uploaded_points (
            series_id  bigint NOT NULL REFERENCES intel.uploaded_series (id) ON DELETE CASCADE,
            period     varchar(10) NOT NULL,
            value      double precision NOT NULL,
            upload_id  bigint NOT NULL REFERENCES intel.uploads (id) ON DELETE CASCADE,
            updated_at timestamptz NOT NULL DEFAULT now(),
            PRIMARY KEY (series_id, period)
        );
        -- الدول المصدّرة لكل عقدة (BACI عبر OEC) تُحسب أسبوعيًا من عامل له إنترنت: الحصص وتركّز HHI.
        CREATE TABLE intel.node_trade (
            node_id     bigint PRIMARY KEY REFERENCES intel.chain_nodes (id) ON DELETE CASCADE,
            hs4         varchar(4) NOT NULL,
            year        integer NOT NULL,
            total_usd   double precision NOT NULL,
            hhi         double precision NOT NULL,
            exporters   jsonb NOT NULL,
            computed_at timestamptz NOT NULL DEFAULT now()
        );
        GRANT SELECT ON intel.node_trade TO app_reader;
        GRANT SELECT, INSERT, UPDATE, DELETE ON intel.node_trade TO ingest_writer;
        GRANT SELECT, INSERT, UPDATE, DELETE ON intel.uploads, intel.uploaded_series, intel.uploaded_points TO app_reader;
        GRANT SELECT ON intel.uploads, intel.uploaded_series, intel.uploaded_points TO ingest_writer;
        GRANT USAGE ON ALL SEQUENCES IN SCHEMA intel TO app_reader, ingest_writer;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS intel.uploaded_points, intel.uploaded_series, intel.uploads');
    }
};
