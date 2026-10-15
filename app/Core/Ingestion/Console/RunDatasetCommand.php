<?php

namespace App\Core\Ingestion\Console;

use App\Core\Ingestion\ConnectorRegistry;
use App\Core\Ingestion\IngestRunner;
use App\Core\Ingestion\Jobs\IngestDatasetJob;
use App\Core\Ingestion\Promoter;
use App\Models\Dataset;
use Illuminate\Console\Command;

class RunDatasetCommand extends Command
{
    protected $signature = 'datasets:run {key : مفتاح المجموعة، مثل imf.weo} {--force : تجاهل فحص التغيّر} {--queue : أرسِل للطابور بدل التنفيذ المباشر}';

    protected $description = 'تشغيل خط الجلب لمجموعة واحدة الآن';

    public function handle(IngestRunner $runner, Promoter $promoter, ConnectorRegistry $registry): int
    {
        $dataset = Dataset::where('key', $this->argument('key'))->first();
        if (! $dataset) {
            $this->error('مجموعة غير موجودة. شغّل datasets:sync أولًا.');

            return self::FAILURE;
        }

        if ($this->option('queue')) {
            $queue = $registry->forDataset($dataset)->queue();
            IngestDatasetJob::dispatch($dataset->id, 'manual', (bool) $this->option('force'), $queue);
            $this->info("أُرسلت إلى طابور {$queue}.");

            return self::SUCCESS;
        }

        $run = $runner->run($dataset, 'manual', (bool) $this->option('force'));
        $this->line("الجلب: {$run->status}".($run->error ? " — {$run->error}" : '')." ({$run->records_count} سجل)");

        if ($run->status === 'staged') {
            $run = $promoter->promote($run);
            $this->line("الاعتماد: {$run->status} ({$run->promoted_count} نقطة جديدة/معدّلة)".($run->error ? " — {$run->error}" : ''));
        }

        return in_array($run->status, ['failed', 'quarantined'], true) ? self::FAILURE : self::SUCCESS;
    }
}
