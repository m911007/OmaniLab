<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        -- الشركات كيانات من نوع جديد.
        ALTER TABLE core.entities DROP CONSTRAINT entities_type_check;
        ALTER TABLE core.entities ADD CONSTRAINT entities_type_check CHECK (type IN ('country','region','group','company','other'));

        -- سمات وصفية للكيان (غير رقمية): حالة، فئة، شكل قانوني، مدينة، تواريخ… لكل سمة مصدرها ووقت تحديثها.
        CREATE TABLE core.entity_attributes (
            entity_id  bigint NOT NULL REFERENCES core.entities(id) ON DELETE CASCADE,
            key        varchar(64) NOT NULL,
            value      jsonb NOT NULL,
            dataset_id bigint REFERENCES ops.datasets(id) ON DELETE SET NULL,
            updated_at timestamptz NOT NULL DEFAULT now(),
            PRIMARY KEY (entity_id, key)
        );
        CREATE INDEX entity_attributes_key_value_idx ON core.entity_attributes (key, (value #>> '{}'));

        CREATE TABLE staging.attributes (
            id          bigserial PRIMARY KEY,
            run_id      bigint NOT NULL REFERENCES ops.ingest_runs(id) ON DELETE CASCADE,
            entity_code varchar(64) NOT NULL,
            key         varchar(64) NOT NULL,
            value       jsonb NOT NULL
        );
        CREATE INDEX staging_attributes_run_idx ON staging.attributes (run_id);

        -- البحث بالاسم (عربي/إنجليزي) على الشركات.
        CREATE EXTENSION IF NOT EXISTS pg_trgm;
        CREATE INDEX entities_name_en_trgm ON core.entities USING gin (lower(name_en) gin_trgm_ops);
        CREATE INDEX entities_name_ar_trgm ON core.entities USING gin (name_ar gin_trgm_ops);
        CREATE INDEX entities_type_idx ON core.entities (type);

        -- رموز الكيانات أطول لدى الشركات (LEI = 20 حرفًا).
        ALTER TABLE core.entities ALTER COLUMN code TYPE varchar(64);
        ALTER TABLE staging.entities ALTER COLUMN code TYPE varchar(64);
        ALTER TABLE staging.records ALTER COLUMN entity_code TYPE varchar(64);

        GRANT SELECT ON core.entity_attributes TO app_reader, ingest_writer;
        GRANT SELECT, INSERT, UPDATE, DELETE ON core.entity_attributes TO promoter;
        GRANT SELECT, INSERT, UPDATE, DELETE ON staging.attributes TO ingest_writer;
        GRANT SELECT, DELETE ON staging.attributes TO promoter;
        GRANT USAGE ON SEQUENCE staging.attributes_id_seq TO ingest_writer;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
        DROP TABLE IF EXISTS staging.attributes, core.entity_attributes;
        DELETE FROM core.entities WHERE type = 'company';
        ALTER TABLE core.entities DROP CONSTRAINT entities_type_check;
        ALTER TABLE core.entities ADD CONSTRAINT entities_type_check CHECK (type IN ('country','region','group','other'));
        SQL);
    }
};
