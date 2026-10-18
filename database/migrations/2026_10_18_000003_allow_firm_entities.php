<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** نوع كيان «firm»: الشركات العالمية من ويكيداتا (منفصل عن «company» المخصص لشركات عُمان من GLEIF). */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        ALTER TABLE core.entities DROP CONSTRAINT IF EXISTS entities_type_check;
        ALTER TABLE core.entities ADD CONSTRAINT entities_type_check CHECK (type IN ('country','region','group','company','place','firm','other'));
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
        DELETE FROM core.entities WHERE type = 'firm';
        ALTER TABLE core.entities DROP CONSTRAINT IF EXISTS entities_type_check;
        ALTER TABLE core.entities ADD CONSTRAINT entities_type_check CHECK (type IN ('country','region','group','company','place','other'));
        SQL);
    }
};
