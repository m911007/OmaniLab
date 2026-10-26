<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ذكاء السوق — طبقة البيانات (المرحلتان 1 و2):
 *   intel.price_series     بطاقة كل سلسلة سعرية (مؤشر × كيان) بنوع السعر والموثوقية والسوق وشروط التسليم والوحدة والعملة والترخيص.
 *   intel.value_chains / chain_nodes / chain_edges / edge_sources / node_series   نموذج سلسلة القيمة القابل للتعديل من لوحة الإدارة.
 *   intel.chain_revisions  لقطة كاملة لكل تعديل (قابلية التدقيق).
 *   intel.regression_checks التحقق الإحصائي من الأوزان مقابل الانحدار التاريخي.
 *   app.organizations / organization_members / user_identities   الجهات والأدوار وهوية دخول قابلة للتوسعة (SSO لاحقًا).
 *   app.supplier_prices    أسعار الموردين لكل جهة (معزولة)، مع منظور مجمّع مُجهّل لا يُظهر إلا ما شاركت فيه 3 جهات فأكثر.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE SCHEMA IF NOT EXISTS intel;

        CREATE TABLE intel.price_series (
            id            bigserial PRIMARY KEY,
            indicator_key varchar(128) NOT NULL,
            entity_code   varchar(32)  NOT NULL,
            label_ar      varchar(200) NOT NULL,
            price_type    varchar(24)  NOT NULL CHECK (price_type IN ('reference', 'index', 'customs_unit_value', 'futures', 'supplier', 'asking', 'derived')),
            confidence    varchar(8)   NOT NULL CHECK (confidence IN ('high', 'medium', 'low')),
            market        varchar(80),
            incoterm      varchar(8) CHECK (incoterm IS NULL OR incoterm IN ('FOB', 'CFR', 'CIF', 'EXW', 'DAP', 'DDP', 'FCA')),
            unit          varchar(40),
            currency      varchar(3),
            frequency     varchar(1) NOT NULL CHECK (frequency IN ('D', 'W', 'M', 'Q', 'A')),
            license_note  varchar(300),
            notes         text,
            is_demo       boolean NOT NULL DEFAULT false,
            enabled       boolean NOT NULL DEFAULT true,
            created_at    timestamptz NOT NULL DEFAULT now(),
            updated_at    timestamptz NOT NULL DEFAULT now(),
            UNIQUE (indicator_key, entity_code)
        );

        CREATE TABLE intel.value_chains (
            id          bigserial PRIMARY KEY,
            slug        varchar(60) NOT NULL UNIQUE,
            name_ar     varchar(160) NOT NULL,
            name_en     varchar(160) NOT NULL,
            description text,
            route_note  text,
            status      varchar(12) NOT NULL DEFAULT 'review' CHECK (status IN ('draft', 'review', 'published')),
            is_demo     boolean NOT NULL DEFAULT false,
            version     integer NOT NULL DEFAULT 1,
            updated_by  bigint REFERENCES app.users (id) ON DELETE SET NULL,
            created_at  timestamptz NOT NULL DEFAULT now(),
            updated_at  timestamptz NOT NULL DEFAULT now()
        );

        CREATE TABLE intel.chain_nodes (
            id        bigserial PRIMARY KEY,
            chain_id  bigint NOT NULL REFERENCES intel.value_chains (id) ON DELETE CASCADE,
            slug      varchar(60) NOT NULL,
            name_ar   varchar(160) NOT NULL,
            name_en   varchar(160) NOT NULL,
            kind      varchar(14) NOT NULL CHECK (kind IN ('raw', 'energy', 'intermediate', 'final', 'consumable', 'conversion')),
            stage     smallint NOT NULL DEFAULT 0,
            unit      varchar(40),
            hs_codes  text[] NOT NULL DEFAULT '{}',
            notes     text,
            UNIQUE (chain_id, slug)
        );

        CREATE TABLE intel.node_series (
            node_id   bigint NOT NULL REFERENCES intel.chain_nodes (id) ON DELETE CASCADE,
            series_id bigint NOT NULL REFERENCES intel.price_series (id) ON DELETE CASCADE,
            role      varchar(12) NOT NULL DEFAULT 'primary' CHECK (role IN ('primary', 'alternative', 'proxy')),
            priority  smallint NOT NULL DEFAULT 1,
            PRIMARY KEY (node_id, series_id)
        );

        -- الوزن = حصة المدخل من تكلفة العقدة الهدف (0–1) بنطاق أدنى/أعلى؛ عامل التحويل = كمية المدخل لكل وحدة من الهدف.
        CREATE TABLE intel.chain_edges (
            id                bigserial PRIMARY KEY,
            chain_id          bigint NOT NULL REFERENCES intel.value_chains (id) ON DELETE CASCADE,
            from_node_id      bigint NOT NULL REFERENCES intel.chain_nodes (id) ON DELETE CASCADE,
            to_node_id        bigint NOT NULL REFERENCES intel.chain_nodes (id) ON DELETE CASCADE,
            weight            numeric(6, 4) CHECK (weight IS NULL OR weight BETWEEN 0 AND 1),
            weight_min        numeric(6, 4) CHECK (weight_min IS NULL OR weight_min BETWEEN 0 AND 1),
            weight_max        numeric(6, 4) CHECK (weight_max IS NULL OR weight_max BETWEEN 0 AND 1),
            weight_basis      varchar(20) NOT NULL DEFAULT 'documented_estimate' CHECK (weight_basis IN ('documented_estimate', 'derived', 'io_table', 'annual_report', 'regression', 'analyst', 'missing')),
            conversion_factor numeric(14, 4),
            conversion_min    numeric(14, 4),
            conversion_max    numeric(14, 4),
            conversion_unit   varchar(60),
            logistics_note    text,
            notes             text,
            updated_by        bigint REFERENCES app.users (id) ON DELETE SET NULL,
            updated_at        timestamptz NOT NULL DEFAULT now(),
            UNIQUE (from_node_id, to_node_id),
            CHECK (from_node_id <> to_node_id),
            CHECK (weight_min IS NULL OR weight_max IS NULL OR weight_min <= weight_max)
        );

        CREATE TABLE intel.edge_sources (
            id          bigserial PRIMARY KEY,
            edge_id     bigint NOT NULL REFERENCES intel.chain_edges (id) ON DELETE CASCADE,
            applies_to  varchar(12) NOT NULL DEFAULT 'weight' CHECK (applies_to IN ('weight', 'conversion', 'both')),
            title       varchar(300) NOT NULL,
            url         varchar(600),
            publisher   varchar(160),
            year        smallint,
            kind        varchar(16) NOT NULL CHECK (kind IN ('industry', 'academic', 'agency', 'company_report', 'io_table', 'vendor', 'trade_press', 'derived')),
            reliability varchar(8) NOT NULL CHECK (reliability IN ('high', 'medium', 'low')),
            excerpt     text
        );

        CREATE TABLE intel.chain_revisions (
            id         bigserial PRIMARY KEY,
            chain_id   bigint NOT NULL REFERENCES intel.value_chains (id) ON DELETE CASCADE,
            version    integer NOT NULL,
            snapshot   jsonb NOT NULL,
            note       varchar(300),
            edited_by  bigint REFERENCES app.users (id) ON DELETE SET NULL,
            created_at timestamptz NOT NULL DEFAULT now()
        );

        CREATE TABLE intel.regression_checks (
            id          bigserial PRIMARY KEY,
            chain_id    bigint NOT NULL REFERENCES intel.value_chains (id) ON DELETE CASCADE,
            node_id     bigint NOT NULL REFERENCES intel.chain_nodes (id) ON DELETE CASCADE,
            computed_at timestamptz NOT NULL DEFAULT now(),
            method      varchar(60) NOT NULL,
            window_from varchar(10),
            window_to   varchar(10),
            n_obs       integer,
            r2          double precision,
            results     jsonb NOT NULL,
            flagged     boolean NOT NULL DEFAULT false
        );
        CREATE INDEX regression_checks_node_idx ON intel.regression_checks (node_id, computed_at DESC);

        -- الجهات والأدوار. الهوية منفصلة عن الحساب كي يُضاف SSO أو الدخول الحكومي لاحقًا بمزوّد جديد لا بإعادة بناء.
        CREATE TABLE app.organizations (
            id         bigserial PRIMARY KEY,
            name_ar    varchar(200) NOT NULL,
            name_en    varchar(200),
            kind       varchar(12) NOT NULL CHECK (kind IN ('government', 'company')),
            country    varchar(3) NOT NULL DEFAULT 'OMN',
            created_at timestamptz NOT NULL DEFAULT now(),
            updated_at timestamptz NOT NULL DEFAULT now()
        );
        CREATE TABLE app.organization_members (
            organization_id bigint NOT NULL REFERENCES app.organizations (id) ON DELETE CASCADE,
            user_id         bigint NOT NULL REFERENCES app.users (id) ON DELETE CASCADE,
            role            varchar(12) NOT NULL CHECK (role IN ('purchaser', 'analyst', 'org_admin')),
            created_at      timestamptz NOT NULL DEFAULT now(),
            PRIMARY KEY (organization_id, user_id)
        );
        CREATE TABLE app.user_identities (
            id         bigserial PRIMARY KEY,
            user_id    bigint NOT NULL REFERENCES app.users (id) ON DELETE CASCADE,
            provider   varchar(30) NOT NULL,
            subject    varchar(255) NOT NULL,
            email      varchar(190),
            created_at timestamptz NOT NULL DEFAULT now(),
            UNIQUE (provider, subject)
        );
        INSERT INTO app.user_identities (user_id, provider, subject, email) SELECT id, 'password', lower(email), lower(email) FROM app.users;

        CREATE TABLE app.supplier_prices (
            id               bigserial PRIMARY KEY,
            organization_id  bigint NOT NULL REFERENCES app.organizations (id) ON DELETE CASCADE,
            node_id          bigint REFERENCES intel.chain_nodes (id) ON DELETE SET NULL,
            hs_code          varchar(12),
            product_label    varchar(250) NOT NULL,
            spec             text,
            supplier_name    varchar(200) NOT NULL,
            supplier_country varchar(3),
            price            numeric(18, 4) NOT NULL CHECK (price > 0),
            currency         varchar(3) NOT NULL,
            unit             varchar(40) NOT NULL,
            quantity         numeric(18, 4),
            incoterm         varchar(8),
            price_date       date NOT NULL,
            valid_until      date,
            source           varchar(12) NOT NULL DEFAULT 'manual' CHECK (source IN ('manual', 'upload', 'integration')),
            document_ref     varchar(120),
            price_omr_per_unit numeric(18, 6),
            created_by       bigint REFERENCES app.users (id) ON DELETE SET NULL,
            created_at       timestamptz NOT NULL DEFAULT now(),
            updated_at       timestamptz NOT NULL DEFAULT now()
        );
        CREATE INDEX supplier_prices_org_idx ON app.supplier_prices (organization_id, node_id, price_date DESC);

        -- مؤشر مجمّع مُجهّل: لا يظهر شهر لعقدة إلا إذا ساهمت فيه 3 جهات فأكثر و5 أسعار فأكثر؛ لا أسماء جهات ولا موردين.
        CREATE VIEW intel.supplier_price_index AS
        SELECT node_id, date_trunc('month', price_date)::date AS month, unit,
               count(*) AS n_prices, count(DISTINCT organization_id) AS n_orgs,
               percentile_cont(0.5) WITHIN GROUP (ORDER BY price_omr_per_unit) AS median_omr,
               percentile_cont(0.25) WITHIN GROUP (ORDER BY price_omr_per_unit) AS p25_omr,
               percentile_cont(0.75) WITHIN GROUP (ORDER BY price_omr_per_unit) AS p75_omr
        FROM app.supplier_prices
        WHERE node_id IS NOT NULL AND price_omr_per_unit IS NOT NULL
        GROUP BY node_id, date_trunc('month', price_date), unit
        HAVING count(DISTINCT organization_id) >= 3 AND count(*) >= 5;

        GRANT USAGE ON SCHEMA intel TO app_reader, ingest_writer;
        GRANT SELECT, INSERT, UPDATE, DELETE ON intel.price_series, intel.value_chains, intel.chain_nodes, intel.node_series, intel.chain_edges, intel.edge_sources TO app_reader;
        GRANT SELECT, INSERT ON intel.chain_revisions TO app_reader;
        GRANT SELECT ON intel.regression_checks, intel.supplier_price_index TO app_reader;
        GRANT SELECT ON ALL TABLES IN SCHEMA intel TO ingest_writer;
        GRANT INSERT, UPDATE, DELETE ON intel.regression_checks TO ingest_writer;
        GRANT INSERT, UPDATE ON intel.price_series TO ingest_writer;
        GRANT USAGE ON ALL SEQUENCES IN SCHEMA intel TO app_reader, ingest_writer;
        GRANT SELECT, INSERT, UPDATE, DELETE ON app.organizations, app.organization_members, app.user_identities, app.supplier_prices TO app_reader;
        GRANT USAGE ON ALL SEQUENCES IN SCHEMA app TO app_reader;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP VIEW IF EXISTS intel.supplier_price_index; DROP TABLE IF EXISTS app.supplier_prices, app.user_identities, app.organization_members, app.organizations; DROP SCHEMA IF EXISTS intel CASCADE;');
    }
};
