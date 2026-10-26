<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * أفكار إضافات مقارنة الأسعار (Keepa/CamelCamelCamel/Google Price insights) داخل المنصة:
 *   intel.price_snapshots  لقطة لكل مقارنة (أدنى ووسيط حسب الدولة والمصدر) لبناء تاريخ السعر وحكم «منخفض/عادي/مرتفع».
 *   app.price_watches      قائمة متابعة لكل مستخدم بسعر مستهدف؛ تُفحص يوميًا وتُعلَّم عند بلوغ الهدف.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE TABLE intel.price_snapshots (
            id          bigserial PRIMARY KEY,
            query       varchar(160) NOT NULL,
            country     varchar(2) NOT NULL,
            source      varchar(24) NOT NULL,
            n_offers    integer NOT NULL,
            min_omr     double precision NOT NULL,
            median_omr  double precision NOT NULL,
            fetched_at  timestamptz NOT NULL DEFAULT now()
        );
        CREATE INDEX price_snapshots_q_idx ON intel.price_snapshots (query, fetched_at DESC);

        CREATE TABLE app.price_watches (
            id              bigserial PRIMARY KEY,
            user_id         bigint NOT NULL REFERENCES app.users (id) ON DELETE CASCADE,
            query           varchar(160) NOT NULL,
            countries       varchar(60) NOT NULL,
            target_omr      double precision,
            last_min_omr    double precision,
            first_min_omr   double precision,
            last_best       jsonb,
            reached_at      timestamptz,
            last_checked_at timestamptz,
            created_at      timestamptz NOT NULL DEFAULT now(),
            UNIQUE (user_id, query, countries)
        );

        GRANT SELECT, INSERT ON intel.price_snapshots TO app_reader, ingest_writer;
        GRANT SELECT, INSERT, UPDATE, DELETE ON app.price_watches TO app_reader;
        GRANT USAGE ON SCHEMA app TO ingest_writer;
        GRANT SELECT, UPDATE ON app.price_watches TO ingest_writer;
        GRANT USAGE ON ALL SEQUENCES IN SCHEMA intel TO app_reader, ingest_writer;
        GRANT USAGE ON ALL SEQUENCES IN SCHEMA app TO app_reader;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS intel.price_snapshots, app.price_watches');
    }
};
