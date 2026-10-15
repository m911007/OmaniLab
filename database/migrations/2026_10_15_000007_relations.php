<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        -- علاقات ثنائية موجّهة بين كيانين (مصدر ← هدف): تجارة بين دولتين، استثمار، تحويلات…
        CREATE TABLE core.relations (
            id               bigserial PRIMARY KEY,
            indicator_id     bigint NOT NULL REFERENCES core.indicators(id),
            source_entity_id bigint NOT NULL REFERENCES core.entities(id),
            target_entity_id bigint NOT NULL REFERENCES core.entities(id),
            period           varchar(10) NOT NULL,
            value            double precision NOT NULL,
            run_id           bigint,
            created_at       timestamptz NOT NULL DEFAULT now(),
            updated_at       timestamptz NOT NULL DEFAULT now(),
            UNIQUE (indicator_id, source_entity_id, target_entity_id, period),
            CHECK (source_entity_id <> target_entity_id)
        );
        CREATE INDEX relations_source_idx ON core.relations (source_entity_id, indicator_id, period);
        CREATE INDEX relations_target_idx ON core.relations (target_entity_id, indicator_id, period);

        CREATE TABLE staging.relations (
            id            bigserial PRIMARY KEY,
            run_id        bigint NOT NULL REFERENCES ops.ingest_runs(id) ON DELETE CASCADE,
            indicator_key varchar(128) NOT NULL,
            source_code   varchar(64)  NOT NULL,
            target_code   varchar(64)  NOT NULL,
            period        varchar(10)  NOT NULL,
            value         double precision NOT NULL,
            rejected      boolean NOT NULL DEFAULT false
        );
        CREATE INDEX staging_relations_run_idx ON staging.relations (run_id);

        GRANT SELECT ON core.relations TO app_reader, ingest_writer;
        GRANT SELECT, INSERT, UPDATE, DELETE ON core.relations TO promoter;
        GRANT USAGE ON SEQUENCE core.relations_id_seq TO promoter;
        GRANT SELECT, INSERT, UPDATE, DELETE ON staging.relations TO ingest_writer;
        GRANT SELECT, UPDATE, DELETE ON staging.relations TO promoter;
        GRANT USAGE ON SEQUENCE staging.relations_id_seq TO ingest_writer;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS staging.relations, core.relations');
    }
};
