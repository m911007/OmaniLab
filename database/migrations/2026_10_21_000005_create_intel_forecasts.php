<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** توقعات الإنذار المبكر بقيمها الفعلية لاحقًا (لقياس الدقة بشفافية). */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE TABLE intel.forecasts (
            id             bigserial PRIMARY KEY,
            chain_id       bigint NOT NULL REFERENCES intel.value_chains (id) ON DELETE CASCADE,
            node_id        bigint NOT NULL REFERENCES intel.chain_nodes (id) ON DELETE CASCADE,
            target_series  varchar(170) NOT NULL,
            issued_at      timestamptz NOT NULL DEFAULT now(),
            base_period    varchar(7) NOT NULL,
            horizon_months smallint NOT NULL,
            expected_pct   double precision NOT NULL,
            low_pct        double precision NOT NULL,
            high_pct       double precision NOT NULL,
            method         varchar(60) NOT NULL,
            drivers        jsonb NOT NULL,
            fit            jsonb NOT NULL,
            actual_pct     double precision,
            evaluated_at   timestamptz,
            UNIQUE (node_id, base_period, horizon_months, method)
        );
        CREATE INDEX forecasts_node_idx ON intel.forecasts (node_id, issued_at DESC);
        GRANT SELECT ON intel.forecasts TO app_reader;
        GRANT SELECT, INSERT, UPDATE ON intel.forecasts TO ingest_writer;
        GRANT USAGE ON ALL SEQUENCES IN SCHEMA intel TO app_reader, ingest_writer;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS intel.forecasts');
    }
};
