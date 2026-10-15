<?php

namespace App\Core\Ingestion\Jobs;

use App\Core\Ingestion\Promoter;
use App\Models\IngestRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** طابور promote — تعمل في حاوية worker-promote بدور promoter (عامل واحد = لا تعارض كتابة). */
class PromoteRunJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $runId)
    {
        $this->onQueue('promote');
    }

    public function handle(Promoter $promoter): void
    {
        $run = IngestRun::find($this->runId);
        if ($run) {
            $promoter->promote($run);
        }
    }
}
