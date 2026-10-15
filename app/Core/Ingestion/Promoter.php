<?php

namespace App\Core\Ingestion;

use App\Core\Freshness\DatasetStatus;
use App\Core\Freshness\FreshnessService;
use App\Core\Notifications\AlertDispatcher;
use App\Core\Validation\Issue;
use App\Core\Validation\Validator;
use App\Models\Dataset;
use App\Models\IngestRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * المرحلة الثانية: تحقق ← اعتماد. يعمل بدور promoter، وهو الكاتب الوحيد في core.
 * فشل التحقق = حجز صحي: لا يُمسّ core وتبقى آخر نسخة سليمة معروضة.
 */
final class Promoter
{
    public function __construct(
        private readonly ConnectorRegistry $registry,
        private readonly Validator $validator,
        private readonly FreshnessService $freshness,
        private readonly AlertDispatcher $alerts,
    ) {}

    public function promote(IngestRun $run): IngestRun
    {
        if ($run->status !== 'staged') {
            return $run;
        }

        $dataset = Dataset::findOrFail($run->dataset_id);
        $definition = $this->registry->forDataset($dataset)->definition();
        $published = ($run->meta['published_at'] ?? null) ? CarbonImmutable::parse($run->meta['published_at']) : null;

        $issues = $this->validator->validate($run->id, $definition, $published, $dataset->source_published_at);
        foreach ($run->meta['conflicts'] ?? [] as $c) {
            $issues[] = new Issue('error', 'duplicate_conflict', "المفتاح {$c['key']} ورد بقيمتين مختلفتين: ".implode(' و ', $c['values']).'.');
        }
        $this->recordIssues($run, $issues);

        $errors = array_values(array_filter($issues, fn (Issue $i) => $i->isError()));
        if ($errors !== []) {
            return $this->quarantine($dataset, $run, $errors);
        }

        $rejected = $this->markRejected($run, $issues);
        if ($rejected > 0) {
            $run->update(['meta' => array_merge($run->meta ?? [], ['rejected_points' => $rejected])]);
        }

        $vanishing = $this->countVanished($dataset, $run);
        $existing = (int) DB::table('core.observations as o')->join('core.indicators as i', 'i.id', '=', 'o.indicator_id')->where('i.dataset_id', $dataset->id)->count()
            + (int) DB::table('core.relations as o')->join('core.indicators as i', 'i.id', '=', 'o.indicator_id')->where('i.dataset_id', $dataset->id)->count();
        $limit = max(100, (int) ($existing * 0.10));
        if ($vanishing > $limit) {
            $issue = new Issue('error', 'mass_removal', "الدفعة تزيل {$vanishing} نقطة معتمدة (أكثر من {$limit}) — يُشتبه بحمولة ناقصة.", null, null, null, ['vanishing' => $vanishing]);
            $this->recordIssues($run, [$issue]);

            return $this->quarantine($dataset, $run, [$issue]);
        }

        return $this->commit($dataset, $run->refresh());
    }

    /**
     * النقاط التي وسمتها قاعدة بـ reject (مثل قفزة وحدة قياس في المصدر) لا تُنشر. تبقى في staging موسومة
     * وموثّقة في validation_issues، وتحمي قيمتها القديمة المعتمدة من الحذف عند إزالة ما اختفى من المصدر.
     *
     * @param  list<Issue>  $issues
     */
    private function markRejected(IngestRun $run, array $issues): int
    {
        $marked = 0;
        foreach ($issues as $i) {
            if ($i->isReject() && $i->indicatorKey && $i->entityCode && $i->period) {
                if (str_contains($i->entityCode, '>')) {
                    [$src, $tgt] = explode('>', $i->entityCode, 2);
                    $marked += DB::table('staging.relations')->where('run_id', $run->id)->where('indicator_key', $i->indicatorKey)
                        ->where('source_code', $src)->where('target_code', $tgt)->where('period', $i->period)->update(['rejected' => true]);

                    continue;
                }
                $marked += DB::table('staging.records')->where('run_id', $run->id)
                    ->where('indicator_key', $i->indicatorKey)->where('entity_code', $i->entityCode)->where('period', $i->period)
                    ->update(['rejected' => true]);
            }
        }

        return $marked;
    }

    /**
     * ما كان معتمدًا لفترات أعاد المصدر نشرها كاملةً وغاب عنها الآن (كيان حُذف أو صُحّح) يُزال، فلا تبقى بقايا قديمة.
     * فترات لم يعد المصدر ينشرها (نافذة متحركة) لا تُمس. النقاط المرفوضة تحمي قيمتها القديمة (تبقى في staging موسومة).
     *
     * يُبنى جدول مؤقت بمفاتيح الدفعة (بحجمها فقط) ويُفهرس ويُحلَّل، ثم يُقارَن به؛ لا نترك الأمر لمخطط استعلام مُحضَّر
     * عام قد يختار مسحًا تربيعيًا (جُرّب: 9 دقائق على مجموعة بلا بيانات سابقة).
     */
    private function stageKeysTable(IngestRun $run): void
    {
        DB::statement('DROP TABLE IF EXISTS _stage_keys');
        DB::statement(sprintf(<<<'SQL'
            CREATE TEMP TABLE _stage_keys ON COMMIT DROP AS
            SELECT i.id AS indicator_id, e.id AS entity_id, r.period
            FROM staging.records r
            JOIN core.indicators i ON i.key = r.indicator_key
            JOIN core.entities e ON e.code = r.entity_code
            WHERE r.run_id = %d
            SQL, $run->id));
        DB::statement('CREATE INDEX ON _stage_keys (indicator_id, period, entity_id)');
        DB::statement('ANALYZE _stage_keys');

        DB::statement('DROP TABLE IF EXISTS _stage_rel_keys');
        DB::statement(sprintf(<<<'SQL'
            CREATE TEMP TABLE _stage_rel_keys ON COMMIT DROP AS
            SELECT i.id AS indicator_id, s.id AS source_id, t.id AS target_id, r.period
            FROM staging.relations r
            JOIN core.indicators i ON i.key = r.indicator_key
            JOIN core.entities s ON s.code = r.source_code
            JOIN core.entities t ON t.code = r.target_code
            WHERE r.run_id = %d
            SQL, $run->id));
        DB::statement('CREATE INDEX ON _stage_rel_keys (indicator_id, period, source_id, target_id)');
        DB::statement('ANALYZE _stage_rel_keys');
    }

    private const VANISHED_REL_WHERE = <<<'SQL'
        EXISTS (SELECT 1 FROM _stage_rel_keys p WHERE p.indicator_id = o.indicator_id AND p.period = o.period)
        AND NOT EXISTS (SELECT 1 FROM _stage_rel_keys k WHERE k.indicator_id = o.indicator_id AND k.period = o.period AND k.source_id = o.source_entity_id AND k.target_id = o.target_entity_id)
        SQL;

    private const VANISHED_WHERE = <<<'SQL'
        EXISTS (SELECT 1 FROM _stage_keys p WHERE p.indicator_id = o.indicator_id AND p.period = o.period)
        AND NOT EXISTS (SELECT 1 FROM _stage_keys k WHERE k.indicator_id = o.indicator_id AND k.period = o.period AND k.entity_id = o.entity_id)
        SQL;

    /** يُستدعى داخل معاملة. الكيانات الجديدة تُدرَج في core.entities قبل الاستدعاء كي تظهر في الجدول المؤقت. */
    private function countVanished(Dataset $dataset, IngestRun $run): int
    {
        return DB::transaction(function () use ($dataset, $run) {
            // الكيانات الجديدة لم تُدرَج بعد: لا تؤثر في النقاط المحتمل حذفها (حذف يخص كيانًا موجودًا أصلًا).
            $this->stageKeysTable($run);

            return (int) DB::selectOne(
                'SELECT count(*) c FROM core.observations o JOIN core.indicators i ON i.id = o.indicator_id WHERE i.dataset_id = ? AND '.self::VANISHED_WHERE,
                [$dataset->id],
            )->c + (int) DB::selectOne(
                'SELECT count(*) c FROM core.relations o JOIN core.indicators i ON i.id = o.indicator_id WHERE i.dataset_id = ? AND '.self::VANISHED_REL_WHERE,
                [$dataset->id],
            )->c;
        });
    }

    /** @param  list<Issue>  $errors */
    private function quarantine(Dataset $dataset, IngestRun $run, array $errors): IngestRun
    {
        $run->update(['status' => 'quarantined', 'finished_at' => now(), 'error' => $errors[0]->message]);
        $this->freshness->markQuarantined($dataset, $errors[0]->message);
        $this->alerts->quarantined($dataset->refresh(), count($errors), $errors[0]->message, $run->id);

        return $run->refresh();
    }

    private function commit(Dataset $dataset, IngestRun $run): IngestRun
    {
        $previous = $dataset->status;
        $changed = 0;

        $removed = 0;
        $attrChanged = 0;
        $skipped = 0;
        $relChanged = 0;
        DB::transaction(function () use ($run, $dataset, &$changed, &$removed, &$attrChanged, &$skipped, &$relChanged) {
            DB::statement(<<<'SQL'
                INSERT INTO core.entities (code, type, name_en, name_ar)
                SELECT code, type, name_en, name_ar FROM staging.entities WHERE run_id = ?
                ON CONFLICT (code) DO UPDATE SET
                    -- أول اسم يُعتمد يبقى: مصدر لاحق لا يطغى عليه (كان «Oman - Free Zones» من منافذ يغطي «Oman»).
                    name_en = CASE WHEN core.entities.name_en = core.entities.code THEN EXCLUDED.name_en ELSE core.entities.name_en END,
                    type = CASE WHEN core.entities.type = 'other' THEN EXCLUDED.type ELSE core.entities.type END,
                    name_ar = COALESCE(core.entities.name_ar, EXCLUDED.name_ar), updated_at = now()
                SQL, [$run->id]);

            DB::statement(<<<'SQL'
                INSERT INTO core.entities (code, type, name_en)
                SELECT DISTINCT r.entity_code, 'other', r.entity_code FROM staging.records r
                WHERE r.run_id = ? AND NOT EXISTS (SELECT 1 FROM core.entities e WHERE e.code = r.entity_code)
                SQL, [$run->id]);

            // السمات الوصفية: تُحدَّث قيمتها فقط إن تغيّرت (فلا يتحرك updated_at بلا سبب).
            $attrChanged = DB::affectingStatement(<<<'SQL'
                INSERT INTO core.entity_attributes (entity_id, key, value, dataset_id)
                SELECT e.id, a.key, a.value, ?
                FROM staging.attributes a JOIN core.entities e ON e.code = a.entity_code
                WHERE a.run_id = ?
                ON CONFLICT (entity_id, key) DO UPDATE SET value = EXCLUDED.value, dataset_id = EXCLUDED.dataset_id, updated_at = now()
                    WHERE core.entity_attributes.value IS DISTINCT FROM EXCLUDED.value
                SQL, [$dataset->id, $run->id]);

            $relChanged = DB::affectingStatement(<<<'SQL'
                INSERT INTO core.relations (indicator_id, source_entity_id, target_entity_id, period, value, run_id)
                SELECT i.id, s.id, t.id, r.period, r.value, r.run_id
                FROM staging.relations r
                JOIN core.indicators i ON i.key = r.indicator_key
                JOIN core.entities s ON s.code = r.source_code
                JOIN core.entities t ON t.code = r.target_code
                WHERE r.run_id = ? AND NOT r.rejected
                ON CONFLICT (indicator_id, source_entity_id, target_entity_id, period) DO UPDATE
                    SET value = EXCLUDED.value, run_id = EXCLUDED.run_id, updated_at = now()
                    WHERE core.relations.value IS DISTINCT FROM EXCLUDED.value
                SQL, [$run->id]);
            $skipped = (int) DB::selectOne(<<<'SQL'
                SELECT count(*) c FROM staging.relations r
                WHERE r.run_id = ? AND (NOT EXISTS (SELECT 1 FROM core.entities e WHERE e.code = r.source_code) OR NOT EXISTS (SELECT 1 FROM core.entities e WHERE e.code = r.target_code))
                SQL, [$run->id])->c;

            $changed = DB::affectingStatement(<<<'SQL'
                INSERT INTO core.observations (indicator_id, entity_id, period, value, run_id)
                SELECT i.id, e.id, r.period, r.value, r.run_id
                FROM staging.records r
                JOIN core.indicators i ON i.key = r.indicator_key
                JOIN core.entities e ON e.code = r.entity_code
                WHERE r.run_id = ? AND NOT r.rejected
                ON CONFLICT (indicator_id, entity_id, period) DO UPDATE
                    SET value = EXCLUDED.value, run_id = EXCLUDED.run_id, updated_at = now()
                    WHERE core.observations.value IS DISTINCT FROM EXCLUDED.value
                SQL, [$run->id]);

            $this->stageKeysTable($run);
            $removed = DB::affectingStatement(
                'DELETE FROM core.observations o USING core.indicators i WHERE i.id = o.indicator_id AND i.dataset_id = ? AND '.self::VANISHED_WHERE,
                [$dataset->id],
            ) + DB::affectingStatement(
                'DELETE FROM core.relations o USING core.indicators i WHERE i.id = o.indicator_id AND i.dataset_id = ? AND '.self::VANISHED_REL_WHERE,
                [$dataset->id],
            );

            DB::table('staging.records')->where('run_id', $run->id)->delete();
            DB::table('staging.entities')->where('run_id', $run->id)->delete();
            DB::table('staging.attributes')->where('run_id', $run->id)->delete();
            DB::table('staging.relations')->where('run_id', $run->id)->delete();
        });
        $changed += $attrChanged + $relChanged;

        $versionChanged = ($run->meta['version'] ?? null) !== $dataset->source_version;

        $dataset->update([
            'status' => DatasetStatus::Fresh,
            'source_version' => $run->meta['version'] ?? $dataset->source_version,
            'source_published_at' => $run->meta['published_at'] ?? $dataset->source_published_at,
            'probe_token' => $run->probe_token,
            'payload_hash' => $run->payload_hash,
            'last_checked_at' => now(),
            'last_success_at' => now(),
            'last_changed_at' => ($changed > 0 || $removed > 0 || $versionChanged) ? now() : $dataset->last_changed_at,
            'next_check_at' => now()->addMinutes($dataset->interval_minutes),
            'consecutive_failures' => 0,
            'last_error' => null,
        ]);

        $run->update([
            'status' => 'promoted', 'promoted_count' => $changed, 'finished_at' => now(),
            'meta' => array_merge($run->meta ?? [], $removed > 0 ? ['removed_points' => $removed] : [], $skipped > 0 ? ['relations_skipped' => $skipped] : []),
        ]);

        $fresh = $dataset->refresh();
        if ($previous !== DatasetStatus::Pending && ($changed > 0 || $removed > 0 || $versionChanged)) {
            $this->alerts->newVersion($fresh, $fresh->source_version, $changed);
        }
        if (in_array($previous, [DatasetStatus::Failing, DatasetStatus::Stale, DatasetStatus::Quarantined], true)) {
            $this->alerts->recovered($fresh);
        }

        return $run->refresh();
    }

    /** @param  list<Issue>  $issues */
    private function recordIssues(IngestRun $run, array $issues): void
    {
        $rows = array_map(fn (Issue $i) => [
            'run_id' => $run->id, 'dataset_id' => $run->dataset_id, 'severity' => $i->severity, 'rule' => $i->rule,
            'indicator_key' => $i->indicatorKey, 'entity_code' => $i->entityCode, 'period' => $i->period,
            'message' => $i->message, 'context' => json_encode($i->context, JSON_UNESCAPED_UNICODE),
        ], $issues);

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('ops.validation_issues')->insert($chunk);
        }
    }
}
