<?php

namespace App\Core\Quality;

use Illuminate\Support\Facades\DB;

/**
 * تدقيق دوري لسلامة البيانات المعتمدة نفسها (لا وقت الدخول فقط): تكرار، يتامى، قيم غير صالحة،
 * رموز كيانات شاذة، مؤشرات فارغة أو راكدة، ومعدل الاستبعاد المرتفع. يعمل بأي دور له قراءة core وops.
 */
final class DataAudit
{
    /** @return list<Finding> */
    public function run(): array
    {
        $findings = [];
        foreach ([
            'duplicate_entity_names', 'leftover_aliases', 'empty_indicators', 'bad_periods',
            'non_finite_values', 'stale_series', 'high_rejection_rate', 'unnamed_arabic_countries',
        ] as $check) {
            $f = $this->{$check}();
            if ($f !== null) {
                $findings[] = $f;
            }
        }

        return $findings;
    }

    /** نفس اسم الدولة برمزين مختلفين: دولة مكررة بسلسلتين. */
    private function duplicate_entity_names(): ?Finding
    {
        $rows = DB::select(<<<'SQL'
            SELECT lower(name_en) n, string_agg(code, ', ' ORDER BY code) codes
            FROM core.entities e
            WHERE type = 'country' AND EXISTS (SELECT 1 FROM core.observations o WHERE o.entity_id = e.id)
            GROUP BY lower(name_en) HAVING count(*) > 1
            SQL);

        return $rows === [] ? null : new Finding('error', 'duplicate_entity_names', 'دولة بأكثر من رمز (تحتاج تعريف alias).', count($rows),
            array_map(fn ($r) => "{$r->n}: {$r->codes}", array_slice($rows, 0, 10)));
    }

    /** رموز مُعرَّفة كـ alias لا يجوز أن تبقى كيانات مستقلة. */
    private function leftover_aliases(): ?Finding
    {
        $aliases = array_keys((array) config('omanilab.entity_aliases', []));
        if ($aliases === []) {
            return null;
        }
        $rows = DB::table('core.entities')->whereIn('code', $aliases)->pluck('code')->all();

        return $rows === [] ? null : new Finding('error', 'leftover_aliases', 'كيانات بأسماء مستعارة لم تُدمج.', count($rows), $rows);
    }

    private function empty_indicators(): ?Finding
    {
        $rows = DB::select(<<<'SQL'
            SELECT i.key FROM core.indicators i
            JOIN ops.datasets d ON d.id = i.dataset_id
            WHERE d.status <> 'pending' AND d.enabled
              AND NOT EXISTS (SELECT 1 FROM core.observations o WHERE o.indicator_id = i.id)
              AND NOT EXISTS (SELECT 1 FROM core.relations r WHERE r.indicator_id = i.id)
            ORDER BY i.key
            SQL);

        return $rows === [] ? null : new Finding('warning', 'empty_indicators', 'مؤشرات معرَّفة بلا أي قيمة بعد الجلب.', count($rows),
            array_map(fn ($r) => $r->key, array_slice($rows, 0, 10)));
    }

    private function bad_periods(): ?Finding
    {
        $n = DB::selectOne(<<<'SQL'
            SELECT count(*) c FROM core.observations
            WHERE period !~ '^\d{4}(-(Q[1-4]|\d{2}(-\d{2})?))?$'
            SQL)->c;

        return $n === 0 ? null : new Finding('error', 'bad_periods', 'فترات بصيغة غير معيارية.', (int) $n);
    }

    private function non_finite_values(): ?Finding
    {
        $n = DB::selectOne("SELECT count(*) c FROM core.observations WHERE value = 'NaN' OR value = 'Infinity' OR value = '-Infinity'")->c;

        return $n === 0 ? null : new Finding('error', 'non_finite_values', 'قيم NaN أو لا نهائية.', (int) $n);
    }

    /** سلسلة شهرية/سنوية لم تتحدث رغم أن مجموعتها تتحدث: المصدر توقف عن نشر هذا المؤشر. */
    private function stale_series(): ?Finding
    {
        $rows = DB::select(<<<'SQL'
            SELECT i.key, i.frequency, max(o.period) last_period
            FROM core.indicators i
            JOIN core.observations o ON o.indicator_id = i.id
            WHERE i.frequency = 'M'
            GROUP BY i.key, i.frequency
            HAVING max(o.period) < to_char(now() - interval '8 months', 'YYYY-MM')
            ORDER BY i.key
            SQL);

        return $rows === [] ? null : new Finding('warning', 'stale_series', 'مؤشرات شهرية آخر نقطة فيها أقدم من 8 أشهر.', count($rows),
            array_map(fn ($r) => "{$r->key} (آخر فترة {$r->last_period})", array_slice($rows, 0, 10)));
    }

    /** مجموعة تستبعد أكثر من 2% من سجلاتها في آخر تشغيل معتمد: المصدر أو القواعد يحتاجان نظرة. */
    private function high_rejection_rate(): ?Finding
    {
        $rows = DB::select(<<<'SQL'
            SELECT d.key, r.records_count, count(v.id) rejected
            FROM ops.datasets d
            JOIN LATERAL (SELECT id, records_count FROM ops.ingest_runs WHERE dataset_id = d.id AND status = 'promoted' ORDER BY id DESC LIMIT 1) r ON true
            JOIN ops.validation_issues v ON v.run_id = r.id AND v.severity = 'reject'
            GROUP BY d.key, r.records_count
            HAVING r.records_count > 0 AND count(v.id)::float / r.records_count > 0.02
            SQL);

        return $rows === [] ? null : new Finding('warning', 'high_rejection_rate', 'مجموعات تستبعد أكثر من 2% من سجلاتها.', count($rows),
            array_map(fn ($r) => "{$r->key}: {$r->rejected} من {$r->records_count}", $rows));
    }

    /** معلومة: دول لها بيانات بلا اسم عربي (تظهر بالإنجليزية في الواجهة). */
    private function unnamed_arabic_countries(): ?Finding
    {
        $rows = DB::select(<<<'SQL'
            SELECT code FROM core.entities e
            WHERE type = 'country' AND name_ar IS NULL AND EXISTS (SELECT 1 FROM core.observations o WHERE o.entity_id = e.id)
            ORDER BY code
            SQL);

        return $rows === [] ? null : new Finding('info', 'unnamed_arabic_countries', 'دول بيانات بلا اسم عربي.', count($rows),
            array_map(fn ($r) => $r->code, array_slice($rows, 0, 15)));
    }
}
