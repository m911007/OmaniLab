<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        -- نقطة وسمتها قاعدة بـ reject: تبقى في staging موسومة (لا تُنشر ولا تُحذف قيمتها القديمة المعتمدة).
        ALTER TABLE staging.records ADD COLUMN rejected boolean NOT NULL DEFAULT false;
        GRANT UPDATE ON staging.records TO promoter;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('ALTER TABLE staging.records DROP COLUMN rejected');
    }
};
