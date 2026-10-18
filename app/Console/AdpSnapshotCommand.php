<?php

namespace App\Console;

use App\Modules\Adp\AdpCatalog;
use App\Modules\Adp\Connectors\AdpEnvironmentConnector;
use App\Modules\Adp\Connectors\AdpLabourConnector;
use App\Modules\Adp\Connectors\AdpPopulationConnector;
use Illuminate\Console\Attributes\AsCommand;
use Illuminate\Console\Command;

#[AsCommand(name: 'adp:snapshot', description: 'يلتقط هوية سلاسل بوابة التنمية العربية (تحتاج الشبكة: تُشغَّل في حاوية الجلب) إلى database/data/adp')]
final class AdpSnapshotCommand extends Command
{
    protected $signature = 'adp:snapshot {dataset? : environment|labour|population (الكل إن حُذف)}';

    public function handle(): int
    {
        $map = ['environment' => AdpEnvironmentConnector::class, 'labour' => AdpLabourConnector::class, 'population' => AdpPopulationConnector::class];
        foreach ($map as $id => $class) {
            if ($this->argument('dataset') && $this->argument('dataset') !== $id) {
                continue;
            }
            $spec = (array) config("adp.datasets.{$id}");
            $catalog = new AdpCatalog($spec);
            $series = [];
            foreach ($catalog->observations((new $class)->download()) as [, $name, $unit]) {
                $series[$catalog->seriesId($id, $name, $unit)] = ['name' => $unit ? "{$name} ({$unit})" : $name, 'unit' => $unit];
            }
            ksort($series);
            file_put_contents(AdpCatalog::snapshotPath($id), json_encode(['series' => $series], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $this->info("{$id}: ".count($series).' سلسلة');
        }

        return self::SUCCESS;
    }
}
