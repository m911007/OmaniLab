<?php

namespace App\Core\Freshness;

use App\Core\Notifications\AlertDispatcher;
use App\Models\Dataset;

/** القاعدة الوحيدة لتحديث حقول الحداثة في سجل المجموعة. */
final class FreshnessService
{
    public function __construct(private readonly AlertDispatcher $alerts) {}

    /** فحص ناجح لم يجد جديدًا: المصدر سليم ومتحقَّق منه الآن. */
    public function markCheckedUnchanged(Dataset $dataset): void
    {
        $was = $dataset->status;
        $keep = in_array($was, [DatasetStatus::Quarantined, DatasetStatus::Manual, DatasetStatus::Disabled], true);

        $dataset->update([
            'last_checked_at' => now(),
            'last_success_at' => $keep ? $dataset->last_success_at : now(),
            'next_check_at' => now()->addMinutes($dataset->interval_minutes),
            'consecutive_failures' => 0,
            'last_error' => $keep ? $dataset->last_error : null,
            'status' => $keep ? $was : DatasetStatus::Fresh,
        ]);

        if (in_array($was, [DatasetStatus::Failing, DatasetStatus::Stale], true)) {
            $this->alerts->recovered($dataset);
        }
    }

    public function markFailed(Dataset $dataset, string $error): void
    {
        $failures = $dataset->consecutive_failures + 1;
        $steps = config('omanilab.ingest.retry_backoff_minutes');
        $delay = $steps[min($failures - 1, count($steps) - 1)];
        $threshold = (int) config('omanilab.ingest.failures_before_alert');

        $dataset->update([
            'last_checked_at' => now(),
            'consecutive_failures' => $failures,
            'last_error' => mb_substr($error, 0, 2000),
            'next_check_at' => now()->addMinutes($delay),
            'status' => $failures >= $threshold ? DatasetStatus::Failing : $dataset->status,
        ]);

        if ($failures >= $threshold) {
            $this->alerts->failing($dataset, $failures, $error);
        }
    }

    public function markQuarantined(Dataset $dataset, string $summary): void
    {
        $dataset->update([
            'status' => DatasetStatus::Quarantined,
            'last_checked_at' => now(),
            'next_check_at' => now()->addMinutes($dataset->interval_minutes),
            'last_error' => mb_substr($summary, 0, 2000),
        ]);
    }

    /** يحوّل المتأخرة إلى stale. يعيد عدد ما تغيّر. */
    public function sweepStale(): int
    {
        $factor = (float) config('omanilab.ingest.stale_factor');
        $count = 0;

        Dataset::query()->where('enabled', true)->where('status', DatasetStatus::Fresh->value)->get()
            ->each(function (Dataset $d) use ($factor, &$count) {
                if ($d->last_success_at && $d->last_success_at->lt(now()->subMinutes((int) ($d->interval_minutes * $factor)))) {
                    $d->update(['status' => DatasetStatus::Stale]);
                    $this->alerts->stale($d);
                    $count++;
                }
            });

        return $count;
    }
}
