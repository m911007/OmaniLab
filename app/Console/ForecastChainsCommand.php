<?php

namespace App\Console;

use App\Modules\ValueChain\EarlyWarning;
use Illuminate\Console\Attributes\AsCommand;
use Illuminate\Console\Command;

#[AsCommand(name: 'chains:forecast', description: 'إصدار توقعات الإنذار المبكر لعقد السلاسل (1 و3 و6 أشهر) وتقييم التوقعات السابقة بالقيم الفعلية')]
final class ForecastChainsCommand extends Command
{
    protected $signature = 'chains:forecast {--backtest=0 : عدد أشهر الاختبار الرجعي (0 = بلا)}';

    public function handle(EarlyWarning $ew): int
    {
        if ((int) $this->option('backtest') > 0) {
            $this->info('اختبار رجعي: '.$ew->backtest((int) $this->option('backtest')).' توقعًا مُقيَّمًا.');
        }
        $r = $ew->issueAndEvaluate();
        $this->info("صدرت {$r['issued']} توقعًا جديدًا، وقُيِّم {$r['evaluated']} توقعًا سابقًا.");

        return self::SUCCESS;
    }
}
