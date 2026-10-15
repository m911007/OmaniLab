<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * يدمج الكيانات المكررة التي دخلت قبل توحيد الرموز (مثل UVK→XKX): ينقل أرصدتها إلى الرمز الموحّد ويحذف الشاذ.
 * آمن للتكرار. المصدر الحقيقي للقائمة config('omanilab.entity_aliases').
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ((array) config('omanilab.entity_aliases', []) as $from => $to) {
            $dup = DB::selectOne('select id, type, name_en, name_ar from core.entities where code = ?', [$from]);
            if (! $dup) {
                continue;
            }

            DB::statement('INSERT INTO core.entities (code, type, name_en, name_ar) VALUES (?, ?, ?, ?) ON CONFLICT (code) DO NOTHING', [$to, $dup->type, $dup->name_en, $dup->name_ar]);
            $canonical = DB::selectOne('select id from core.entities where code = ?', [$to])->id;

            // نفس (المؤشر، الفترة) عند الكيانين: يُبقى الرصيد الموحّد ويُحذف رصيد الشاذ.
            DB::statement(<<<'SQL'
                DELETE FROM core.observations o USING core.observations k
                WHERE o.entity_id = ? AND k.entity_id = ? AND k.indicator_id = o.indicator_id AND k.period = o.period
                SQL, [$dup->id, $canonical]);
            DB::statement('UPDATE core.observations SET entity_id = ? WHERE entity_id = ?', [$canonical, $dup->id]);
            DB::statement('DELETE FROM core.entities WHERE id = ?', [$dup->id]);
        }
    }

    public function down(): void {}
};
