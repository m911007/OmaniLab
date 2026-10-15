<?php

namespace App\Core\Ingestion\Console;

use App\Core\Freshness\DatasetStatus;
use App\Core\Notifications\AlertDispatcher;
use App\Models\Dataset;
use App\Models\IngestRun;
use Illuminate\Console\Command;

class DigestCommand extends Command
{
    protected $signature = 'alerts:digest';

    protected $description = 'إرسال الملخص اليومي إلى تيليجرام';

    public function handle(AlertDispatcher $alerts): int
    {
        $datasets = Dataset::where('enabled', true)->get();
        $byStatus = $datasets->groupBy(fn (Dataset $d) => $d->status->value)->map->count();
        $runs = IngestRun::where('started_at', '>=', now()->subDay())->get()->groupBy('status')->map->count();

        $lines = ['📊 <b>الملخص اليومي — المخبر العماني</b>', ''];
        $lines[] = "المجموعات: {$datasets->count()} — محدّثة: ".($byStatus[DatasetStatus::Fresh->value] ?? 0);
        $lines[] = 'تشغيلات آخر 24 ساعة: اعتماد '.($runs['promoted'] ?? 0).'، دون تغيير '.($runs['unchanged'] ?? 0).'، حجر '.($runs['quarantined'] ?? 0).'، فشل '.($runs['failed'] ?? 0);

        $attention = $datasets->filter(fn (Dataset $d) => ! in_array($d->status, [DatasetStatus::Fresh, DatasetStatus::Manual, DatasetStatus::Disabled], true));
        if ($attention->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '<b>تحتاج انتباهًا:</b>';
            foreach ($attention as $d) {
                $lines[] = "• {$d->name_ar} — {$d->status->labelAr()}";
            }
        }

        $alerts->digest(implode("\n", $lines));
        $this->info('أُرسل الملخص.');

        return self::SUCCESS;
    }
}
