<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        ALTER TABLE ops.validation_issues DROP CONSTRAINT validation_issues_severity_check;
        ALTER TABLE ops.validation_issues ADD CONSTRAINT validation_issues_severity_check
            CHECK (severity IN ('warning','reject','error'));
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
        DELETE FROM ops.validation_issues WHERE severity = 'reject';
        ALTER TABLE ops.validation_issues DROP CONSTRAINT validation_issues_severity_check;
        ALTER TABLE ops.validation_issues ADD CONSTRAINT validation_issues_severity_check CHECK (severity IN ('warning','error'));
        SQL);
    }
};
