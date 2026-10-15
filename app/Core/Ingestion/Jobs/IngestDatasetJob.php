<?php

namespace App\Core\Ingestion\Jobs;

use App\Core\Ingestion\IngestRunner;
use App\Models\Dataset;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/** طابور ingest أو ingest-heavy — تعمل في حاويتي worker-ingest وworker-ingest-heavy بدور ingest_writer. */
class IngestDatasetJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $datasetId, public string $trigger = 'schedule', public bool $force = false, string $queue = 'ingest')
    {
        $this->onQueue($queue);
    }

    public function handle(IngestRunner $runner): void
    {
        $dataset = Dataset::find($this->datasetId);
        if (! $dataset || ! $dataset->enabled) {
            Log::warning("[ingest] تخطّي المهمة: المجموعة #{$this->datasetId} غير موجودة أو معطّلة");

            return;
        }

        Log::info("[ingest] بدء {$dataset->key} (trigger={$this->trigger})");
        $run = $runner->run($dataset, $this->trigger, $this->force);
        Log::info("[ingest] انتهاء {$dataset->key}: run #{$run->id} {$run->status}");

        if ($run->status === 'staged') {
            PromoteRunJob::dispatch($run->id);
        }
    }
}
