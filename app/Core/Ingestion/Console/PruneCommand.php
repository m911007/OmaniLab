<?php

namespace App\Core\Ingestion\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneCommand extends Command
{
    protected $signature = 'datasets:prune {--keep=3 : عدد الحمولات الخام المحفوظة لكل مجموعة}';

    protected $description = 'حذف الحمولات الخام القديمة وسجلات staging المتبقية القديمة (بدور ingest_writer)';

    public function handle(): int
    {
        $keep = (int) $this->option('keep');

        $raw = DB::delete(<<<'SQL'
            DELETE FROM raw.payloads WHERE id IN (
                SELECT id FROM (
                    SELECT id, row_number() OVER (PARTITION BY dataset_id ORDER BY id DESC) rn FROM raw.payloads
                ) t WHERE rn > ?
            )
            SQL, [$keep]);

        $staging = DB::delete("DELETE FROM staging.records WHERE run_id IN (SELECT id FROM ops.ingest_runs WHERE started_at < now() - interval '14 days')");
        DB::delete("DELETE FROM staging.relations WHERE run_id IN (SELECT id FROM ops.ingest_runs WHERE started_at < now() - interval '14 days')");
        DB::delete("DELETE FROM staging.attributes WHERE run_id IN (SELECT id FROM ops.ingest_runs WHERE started_at < now() - interval '14 days')");
        DB::delete("DELETE FROM staging.entities WHERE run_id IN (SELECT id FROM ops.ingest_runs WHERE started_at < now() - interval '14 days')");

        $this->info("حُذف {$raw} حمولة خام و{$staging} سجل staging قديم.");

        return self::SUCCESS;
    }
}
