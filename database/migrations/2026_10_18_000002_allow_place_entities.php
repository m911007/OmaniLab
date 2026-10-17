<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** نوع كيان «place»: مؤسسات ومرافق وموارد تُجلب من OpenStreetMap (جامعات، مدارس، دوائر حكومية، محطات، سدود…). */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        ALTER TABLE core.entities DROP CONSTRAINT IF EXISTS entities_type_check;
        ALTER TABLE core.entities ADD CONSTRAINT entities_type_check CHECK (type IN ('country','region','group','company','place','other'));
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
        DELETE FROM core.entities WHERE type = 'place';
        ALTER TABLE core.entities DROP CONSTRAINT IF EXISTS entities_type_check;
        ALTER TABLE core.entities ADD CONSTRAINT entities_type_check CHECK (type IN ('country','region','group','company','other'));
        SQL);
    }
};
