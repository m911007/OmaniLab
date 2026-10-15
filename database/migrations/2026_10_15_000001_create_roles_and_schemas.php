<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * طبقات قاعدة البيانات وأدوارها:
 *   raw      الاستجابات الخام كما وصلت            (يكتب: ingest_writer)
 *   staging  السجلات بعد التحليل والتحقق         (يكتب: ingest_writer)
 *   core     البيانات المعتمدة المعروضة          (يكتب: promoter فقط)
 *   ops      سجل المجموعات والتشغيل والمشكلات    (يكتب: ingest_writer وpromoter)
 */
return new class extends Migration
{
    private const ROLES = ['app_reader', 'ingest_writer', 'promoter'];

    public function up(): void
    {
        foreach (['raw', 'staging', 'core', 'ops'] as $schema) {
            DB::statement("CREATE SCHEMA IF NOT EXISTS {$schema}");
        }

        foreach (self::ROLES as $role) {
            $password = (string) config("omanilab.db_roles.{$role}");
            $exists = DB::selectOne('select 1 as x from pg_roles where rolname = ?', [$role]);
            $quoted = DB::getPdo()->quote($password);

            DB::statement($exists
                ? "ALTER ROLE {$role} WITH LOGIN PASSWORD {$quoted}"
                : "CREATE ROLE {$role} WITH LOGIN PASSWORD {$quoted}");
        }

        $db = DB::selectOne('select current_database() as d')->d;
        foreach (self::ROLES as $role) {
            DB::statement("GRANT CONNECT ON DATABASE \"{$db}\" TO {$role}");
            DB::statement("GRANT USAGE ON SCHEMA raw, staging, core, ops TO {$role}");
        }
    }

    public function down(): void
    {
        foreach (self::ROLES as $role) {
            DB::statement("DROP OWNED BY {$role}");
            DB::statement("DROP ROLE IF EXISTS {$role}");
        }
        DB::statement('DROP SCHEMA IF EXISTS raw, staging, core, ops CASCADE');
    }
};
