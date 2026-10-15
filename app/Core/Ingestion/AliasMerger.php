<?php

namespace App\Core\Ingestion;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/**
 * يدمج الكيانات التي دخلت برمز شاذ قبل أن يُعرَّف له alias (config omanilab.entity_aliases): ينقل قياساتها وعلاقاتها وسماتها
 * إلى الرمز الموحَّد ثم يحذف الشاذ. ما تعارض مع قيمة موجودة عند الموحَّد يُحذف (يبقى الموحَّد). آمن للتكرار.
 * يعمل بدور المالك لأنه يكتب في core، فيُستدعى من حاوية الترحيل لا من عمال الجلب. الجلب الجديد يوحّد الرموز عند الإدخال أصلًا.
 */
final class AliasMerger
{
    /** @return int عدد الكيانات الشاذة المدموجة */
    public function merge(): int
    {
        $merged = 0;
        foreach ((array) config('omanilab.entity_aliases', []) as $from => $to) {
            DB::connection('admin')->transaction(function (Connection $db) use ($from, $to, &$merged) {
                $dup = $db->selectOne('SELECT id, type, name_en, name_ar FROM core.entities WHERE code = ?', [$from]);
                if (! $dup) {
                    return;
                }
                $db->statement('INSERT INTO core.entities (code, type, name_en, name_ar) VALUES (?, ?, ?, ?) ON CONFLICT (code) DO NOTHING', [$to, $dup->type, $dup->name_en, $dup->name_ar]);
                $canonical = $db->selectOne('SELECT id FROM core.entities WHERE code = ?', [$to])->id;
                $db->statement('UPDATE core.entities SET name_ar = COALESCE(name_ar, ?) WHERE id = ?', [$dup->name_ar, $canonical]);

                $db->statement('DELETE FROM core.observations o USING core.observations k WHERE o.entity_id = ? AND k.entity_id = ? AND k.indicator_id = o.indicator_id AND k.period = o.period', [$dup->id, $canonical]);
                $db->statement('UPDATE core.observations SET entity_id = ? WHERE entity_id = ?', [$canonical, $dup->id]);

                $db->statement('DELETE FROM core.entity_attributes a USING core.entity_attributes k WHERE a.entity_id = ? AND k.entity_id = ? AND k.key = a.key', [$dup->id, $canonical]);
                $db->statement('UPDATE core.entity_attributes SET entity_id = ? WHERE entity_id = ?', [$canonical, $dup->id]);

                foreach ([['source_entity_id', 'target_entity_id'], ['target_entity_id', 'source_entity_id']] as [$moved, $other]) {
                    $db->statement("DELETE FROM core.relations r USING core.relations k WHERE r.{$moved} = ? AND k.{$moved} = ? AND k.{$other} = r.{$other} AND k.indicator_id = r.indicator_id AND k.period = r.period", [$dup->id, $canonical]);
                    $db->statement("UPDATE core.relations SET {$moved} = ? WHERE {$moved} = ?", [$canonical, $dup->id]);
                }

                $db->statement('DELETE FROM core.entities WHERE id = ?', [$dup->id]);
                $merged++;
            });
        }

        return $merged;
    }
}
