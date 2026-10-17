<?php

namespace Tests;

use Illuminate\Support\Facades\DB;

/**
 * اختبارات على PostgreSQL حقيقي (الجداول في schemas منفصلة فلا يصلح RefreshDatabase).
 * قاعدة omanilab_test تُنشأ مرة واحدة: CREATE DATABASE omanilab_test;
 */
abstract class DatabaseTestCase extends TestCase
{
    private static bool $migrated = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$migrated) {
            DB::unprepared('DROP SCHEMA IF EXISTS raw, staging, core, ops, app CASCADE; DROP TABLE IF EXISTS migrations');
            $this->artisan('migrate', ['--database' => 'admin', '--force' => true])->assertSuccessful();
            self::$migrated = true;
        }

        DB::unprepared('TRUNCATE core.observations, core.indicators, core.entities, core.sources, ops.datasets, ops.ingest_runs, ops.validation_issues, raw.payloads, staging.records, staging.entities RESTART IDENTITY CASCADE');
    }
}
