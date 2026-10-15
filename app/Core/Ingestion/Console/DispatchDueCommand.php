<?php

namespace App\Core\Ingestion\Console;

use App\Core\Ingestion\ConnectorRegistry;
use App\Core\Ingestion\Jobs\IngestDatasetJob;
use App\Core\Ingestion\RunJanitor;
use App\Models\Dataset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DispatchDueCommand extends Command
{
    protected $signature = 'datasets:dispatch';

    protected $description = 'إرسال المجموعات التي حان موعد فحصها إلى طابور الجلب';

    public function handle(RunJanitor $janitor, ConnectorRegistry $registry): int
    {
        $abandoned = $janitor->sweep();
        if ($abandoned > 0) {
            $this->warn("نُظِّف {$abandoned} تشغيل متروك.");
        }

        $due = Dataset::query()->where('enabled', true)
            ->where(fn ($q) => $q->whereNull('next_check_at')->orWhere('next_check_at', '<=', now()))
            ->get();

        foreach ($due as $dataset) {
            // حجز مؤقت يمنع الإرسال المزدوج؛ يُستبدل بالموعد الحقيقي عند انتهاء التشغيل.
            $dataset->update(['next_check_at' => now()->addMinutes(30)]);
            $queue = $registry->forDataset($dataset)->queue();
            IngestDatasetJob::dispatch($dataset->id, 'schedule', false, $queue);
            Log::info("[dispatch] {$dataset->key} (#{$dataset->id}) → {$queue}");
        }

        $this->info("أُرسلت {$due->count()} مجموعة.");

        return self::SUCCESS;
    }
}
