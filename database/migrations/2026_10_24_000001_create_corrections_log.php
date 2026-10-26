<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * سجل تصحيحات البيانات وانقطاعاتها المشتبه بها (المحور 8 من خطة «من الأرقام إلى القرار»):
 * applied = صُحّحت آليًا قبل النشر (مثل نشر المصدر بالمليار بدل المليون)، suspected = اكتُشفت بالفحص الأسبوعي وتنتظر المراجعة.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE TABLE ops.corrections (
            id            bigserial PRIMARY KEY,
            indicator_key varchar(170) NOT NULL,
            entity_code   varchar(64) NOT NULL,
            period        varchar(10) NOT NULL,
            kind          varchar(40) NOT NULL,
            status        varchar(12) NOT NULL CHECK (status IN ('applied', 'suspected', 'dismissed')),
            original      double precision NOT NULL,
            corrected     double precision,
            note          text NOT NULL,
            detected_at   timestamptz NOT NULL DEFAULT now(),
            UNIQUE (indicator_key, entity_code, period, kind)
        );
        CREATE INDEX corrections_recent_idx ON ops.corrections (detected_at DESC);
        GRANT SELECT ON ops.corrections TO app_reader;
        GRANT SELECT, INSERT, UPDATE, DELETE ON ops.corrections TO ingest_writer;
        GRANT USAGE ON SEQUENCE ops.corrections_id_seq TO ingest_writer;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS ops.corrections');
    }
};
