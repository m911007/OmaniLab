<?php

namespace App\Core\Ingestion\Console;

use App\Core\Insights\SignalMonitor;
use App\Core\Notifications\AlertDispatcher;
use Illuminate\Console\Command;

class SignalsCommand extends Command
{
    protected $signature = 'signals:check';

    protected $description = 'تقييم إشارات السوق وإرسال الجديد منها إلى تيليجرام (مرة لكل إشارة وفترة)';

    public function handle(SignalMonitor $monitor, AlertDispatcher $alerts): int
    {
        $sent = 0;
        foreach ($monitor->active() as $signal) {
            $sent += $alerts->signal($signal) ? 1 : 0;
        }
        $this->info("إشارات أُرسلت: {$sent}");

        return self::SUCCESS;
    }
}
