<?php

namespace App\Core\Ingestion\Console;

use App\Core\Ingestion\DefinitionSync;
use Illuminate\Console\Command;

class SyncDefinitionsCommand extends Command
{
    protected $signature = 'datasets:sync';

    protected $description = 'مزامنة تعريفات المصادر والمجموعات والمؤشرات من الكود إلى قاعدة البيانات (بدور المالك)';

    public function handle(DefinitionSync $sync): int
    {
        $r = $sync->sync();
        $this->info("تمت المزامنة: {$r['datasets']} مجموعة، {$r['indicators']} مؤشر.");

        return self::SUCCESS;
    }
}
