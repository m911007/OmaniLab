<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * سلاسل العُمانيين في ncsi.labour قبل 2020 بتغطية أضيق (قفزة +54% عند 2020 ليست نموًا): الموصل لم يعد يجلبها،
 * وهذا الترحيل يزيل ما حُمّل منها قبل ذلك كي لا تُوصل بسلسلة مختلفة التعريف.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            DELETE FROM core.observations o USING core.indicators i
            WHERE o.indicator_id = i.id AND i.key LIKE 'ncsi.LAB\_%OMANI%' AND o.period < '2020'
            SQL);
    }

    public function down(): void {}
};
