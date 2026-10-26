<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** حالة «excluded» لنقاط خاطئة في المصدر تُستبعد قبل النشر (config/data_exclusions.php). */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        ALTER TABLE ops.corrections DROP CONSTRAINT corrections_status_check;
        ALTER TABLE ops.corrections ADD CONSTRAINT corrections_status_check CHECK (status IN ('applied', 'suspected', 'dismissed', 'excluded'));
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
        DELETE FROM ops.corrections WHERE status = 'excluded';
        ALTER TABLE ops.corrections DROP CONSTRAINT corrections_status_check;
        ALTER TABLE ops.corrections ADD CONSTRAINT corrections_status_check CHECK (status IN ('applied', 'suspected', 'dismissed'));
        SQL);
    }
};
