<?php

namespace App\Core\Ingestion;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * تشغيل بدأ ولم ينتهِ (انقطع العامل أو أُعيد تشغيل الحاوية) يبقى running/staged إلى الأبد ويحتفظ بسجلات staging.
 * يُعلَّم هنا فاشلًا ويُنظَّف staging الخاص به، فيعيد المُجدوِل المحاولة في دورته التالية.
 */
final class RunJanitor
{
    public function __construct(private readonly int $abandonAfterMinutes = 45) {}

    public function sweep(): int
    {
        $ids = DB::table('ops.ingest_runs')
            ->whereIn('status', ['running', 'staged'])
            ->where('started_at', '<', now()->subMinutes($this->abandonAfterMinutes))
            ->pluck('id')->all();

        foreach ($ids as $id) {
            DB::table('ops.ingest_runs')->where('id', $id)->update([
                'status' => 'failed',
                'error' => "تشغيل متروك: لم يكتمل خلال {$this->abandonAfterMinutes} دقيقة (انقطاع عامل أو إعادة تشغيل).",
                'finished_at' => now(),
            ]);
            DB::table('staging.records')->where('run_id', $id)->delete();
            DB::table('staging.entities')->where('run_id', $id)->delete();
            DB::table('staging.attributes')->where('run_id', $id)->delete();
            DB::table('staging.relations')->where('run_id', $id)->delete();
        }

        if ($ids !== []) {
            Log::warning('نُظِّفت تشغيلات متروكة: '.implode(',', $ids));
        }

        return count($ids);
    }
}
