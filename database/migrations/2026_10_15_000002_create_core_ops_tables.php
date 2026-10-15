<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        -- ============ core: البيانات المعتمدة ============
        CREATE TABLE core.sources (
            id          bigserial PRIMARY KEY,
            key         varchar(64)  NOT NULL UNIQUE,
            name_ar     varchar(255) NOT NULL,
            name_en     varchar(255) NOT NULL,
            publisher   varchar(255),
            url         varchar(500),
            license     varchar(255),
            reliability varchar(16)  NOT NULL DEFAULT 'official'
                        CHECK (reliability IN ('official','secondary','agent','manual','unverified')),
            created_at  timestamptz NOT NULL DEFAULT now(),
            updated_at  timestamptz NOT NULL DEFAULT now()
        );

        CREATE TABLE core.entities (
            id         bigserial PRIMARY KEY,
            code       varchar(32)  NOT NULL UNIQUE,   -- ISO3 للدول، ورمز المصدر للمجموعات
            type       varchar(16)  NOT NULL DEFAULT 'country'
                       CHECK (type IN ('country','region','group','other')),
            name_en    varchar(255) NOT NULL,
            name_ar    varchar(255),
            meta       jsonb NOT NULL DEFAULT '{}',
            created_at timestamptz NOT NULL DEFAULT now(),
            updated_at timestamptz NOT NULL DEFAULT now()
        );

        -- ============ ops: التحكم والتشغيل ============
        CREATE TABLE ops.datasets (
            id                   bigserial PRIMARY KEY,
            key                  varchar(96)  NOT NULL UNIQUE,
            source_id            bigint NOT NULL REFERENCES core.sources(id),
            name_ar              varchar(255) NOT NULL,
            name_en              varchar(255) NOT NULL,
            section              varchar(64)  NOT NULL,
            connector            varchar(255) NOT NULL,
            reliability          varchar(16)  NOT NULL DEFAULT 'official',
            interval_minutes     integer NOT NULL,                 -- الدورية المتوقعة للفحص
            enabled              boolean NOT NULL DEFAULT true,
            status               varchar(16) NOT NULL DEFAULT 'pending'
                                 CHECK (status IN ('pending','fresh','stale','quarantined','failing','manual','disabled')),
            source_version       varchar(255),                      -- مثل: World Economic Outlook (October 2026)
            source_published_at  timestamptz,
            probe_token          varchar(255),                      -- آخر بصمة فحص مُرحَّلة
            payload_hash         char(64),                          -- SHA-256 لآخر حمولة مُرحَّلة
            last_checked_at      timestamptz,
            last_changed_at      timestamptz,
            last_success_at      timestamptz,
            next_check_at        timestamptz,
            consecutive_failures integer NOT NULL DEFAULT 0,
            last_error           text,
            created_at           timestamptz NOT NULL DEFAULT now(),
            updated_at           timestamptz NOT NULL DEFAULT now()
        );
        CREATE INDEX datasets_due_idx ON ops.datasets (next_check_at) WHERE enabled;

        CREATE TABLE core.indicators (
            id          bigserial PRIMARY KEY,
            key         varchar(128) NOT NULL UNIQUE,              -- مثل: imf.NGDP_RPCH
            dataset_id  bigint NOT NULL REFERENCES ops.datasets(id),
            name_ar     varchar(255) NOT NULL,
            name_en     varchar(255) NOT NULL,
            unit        varchar(64),
            frequency   varchar(8) NOT NULL DEFAULT 'A' CHECK (frequency IN ('A','Q','M','D')),
            description text,
            config      jsonb NOT NULL DEFAULT '{}',
            created_at  timestamptz NOT NULL DEFAULT now(),
            updated_at  timestamptz NOT NULL DEFAULT now()
        );

        CREATE TABLE core.observations (
            id           bigserial PRIMARY KEY,
            indicator_id bigint NOT NULL REFERENCES core.indicators(id),
            entity_id    bigint NOT NULL REFERENCES core.entities(id),
            period       varchar(10) NOT NULL,                     -- 2025 | 2025-Q3 | 2025-09 | 2025-09-30
            value        double precision NOT NULL,
            run_id       bigint,
            created_at   timestamptz NOT NULL DEFAULT now(),
            updated_at   timestamptz NOT NULL DEFAULT now(),
            UNIQUE (indicator_id, entity_id, period)
        );
        CREATE INDEX observations_entity_idx ON core.observations (entity_id, indicator_id);

        CREATE TABLE ops.ingest_runs (
            id            bigserial PRIMARY KEY,
            dataset_id    bigint NOT NULL REFERENCES ops.datasets(id) ON DELETE CASCADE,
            status        varchar(16) NOT NULL DEFAULT 'running'
                          CHECK (status IN ('running','unchanged','staged','quarantined','promoted','failed')),
            trigger       varchar(16) NOT NULL DEFAULT 'schedule',
            probe_token   varchar(255),
            payload_hash  char(64),
            records_count integer NOT NULL DEFAULT 0,
            promoted_count integer NOT NULL DEFAULT 0,
            error         text,
            meta          jsonb NOT NULL DEFAULT '{}',
            started_at    timestamptz NOT NULL DEFAULT now(),
            finished_at   timestamptz
        );
        CREATE INDEX ingest_runs_dataset_idx ON ops.ingest_runs (dataset_id, started_at DESC);

        CREATE TABLE ops.validation_issues (
            id            bigserial PRIMARY KEY,
            run_id        bigint NOT NULL REFERENCES ops.ingest_runs(id) ON DELETE CASCADE,
            dataset_id    bigint NOT NULL REFERENCES ops.datasets(id) ON DELETE CASCADE,
            severity      varchar(8) NOT NULL CHECK (severity IN ('warning','error')),
            rule          varchar(48) NOT NULL,
            indicator_key varchar(128),
            entity_code   varchar(32),
            period        varchar(10),
            message       text NOT NULL,
            context       jsonb NOT NULL DEFAULT '{}',
            created_at    timestamptz NOT NULL DEFAULT now()
        );
        CREATE INDEX validation_issues_run_idx ON ops.validation_issues (run_id);

        -- ============ raw: الاستجابات الخام ============
        CREATE TABLE raw.payloads (
            id           bigserial PRIMARY KEY,
            run_id       bigint NOT NULL REFERENCES ops.ingest_runs(id) ON DELETE CASCADE,
            dataset_id   bigint NOT NULL REFERENCES ops.datasets(id) ON DELETE CASCADE,
            sha256       char(64) NOT NULL,
            bytes        integer NOT NULL,
            content_type varchar(100) NOT NULL DEFAULT 'application/json',
            body         text NOT NULL,
            created_at   timestamptz NOT NULL DEFAULT now()
        );
        CREATE INDEX payloads_dataset_idx ON raw.payloads (dataset_id, created_at DESC);

        -- ============ staging: السجلات الموحّدة قبل الاعتماد ============
        CREATE TABLE staging.records (
            id            bigserial PRIMARY KEY,
            run_id        bigint NOT NULL REFERENCES ops.ingest_runs(id) ON DELETE CASCADE,
            indicator_key varchar(128) NOT NULL,
            entity_code   varchar(32)  NOT NULL,
            period        varchar(10)  NOT NULL,
            value         double precision NOT NULL
        );
        CREATE INDEX staging_records_run_idx ON staging.records (run_id);

        CREATE TABLE staging.entities (
            id       bigserial PRIMARY KEY,
            run_id   bigint NOT NULL REFERENCES ops.ingest_runs(id) ON DELETE CASCADE,
            code     varchar(32)  NOT NULL,
            type     varchar(16)  NOT NULL DEFAULT 'country',
            name_en  varchar(255) NOT NULL,
            name_ar  varchar(255)
        );
        CREATE INDEX staging_entities_run_idx ON staging.entities (run_id);

        -- ============ الصلاحيات (أقل صلاحية لكل دور) ============
        -- app_reader: قراءة core وسجل التشغيل فقط؛ لا يرى raw ولا staging.
        GRANT SELECT ON ALL TABLES IN SCHEMA core TO app_reader;
        GRANT SELECT ON ops.datasets, ops.ingest_runs, ops.validation_issues TO app_reader;

        -- ingest_writer: يكتب raw/staging وسجل التشغيل، ويقرأ تعريفات core ولا يكتب فيها.
        GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA raw, staging TO ingest_writer;
        GRANT SELECT ON ALL TABLES IN SCHEMA core TO ingest_writer;
        GRANT SELECT, UPDATE ON ops.datasets TO ingest_writer;
        GRANT SELECT, INSERT, UPDATE ON ops.ingest_runs TO ingest_writer;
        GRANT SELECT, INSERT ON ops.validation_issues TO ingest_writer;
        GRANT USAGE ON ALL SEQUENCES IN SCHEMA raw, staging, ops TO ingest_writer;

        -- promoter: الكاتب الوحيد في core.
        GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA core TO promoter;
        GRANT SELECT, DELETE ON ALL TABLES IN SCHEMA staging TO promoter;
        GRANT SELECT ON raw.payloads TO promoter;
        GRANT SELECT, UPDATE ON ops.datasets TO promoter;
        GRANT SELECT, INSERT, UPDATE ON ops.ingest_runs TO promoter;
        GRANT SELECT, INSERT ON ops.validation_issues TO promoter;
        GRANT USAGE ON ALL SEQUENCES IN SCHEMA core, ops TO promoter;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
        DROP TABLE IF EXISTS staging.entities, staging.records, raw.payloads,
            ops.validation_issues, ops.ingest_runs, core.observations, core.indicators,
            ops.datasets, core.entities, core.sources CASCADE;
        SQL);
    }
};
