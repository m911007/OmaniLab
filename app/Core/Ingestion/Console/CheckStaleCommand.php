<?php

namespace App\Core\Ingestion\Console;

use App\Core\Freshness\FreshnessService;
use Illuminate\Console\Command;

class CheckStaleCommand extends Command
{
    protected $signature = 'datasets:check-stale';

    protected $description = 'تحويل المجموعات المتأخرة عن دورتها إلى stale وإرسال تنبيه';

    public function handle(FreshnessService $freshness): int
    {
        $this->info('متأخرة جديدة: '.$freshness->sweepStale());

        return self::SUCCESS;
    }
}
