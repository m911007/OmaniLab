<?php

namespace App\Console;

use App\Modules\ValueChain\WeightValidator;
use Illuminate\Console\Attributes\AsCommand;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[AsCommand(name: 'chains:validate', description: 'التحقق الإحصائي من أوزان سلاسل القيمة بانحدار تاريخي وتنبيه المحلل عند التعارض')]
final class ValidateChainsCommand extends Command
{
    protected $signature = 'chains:validate {slug? : سلسلة واحدة}';

    public function handle(WeightValidator $validator): int
    {
        $chains = DB::table('intel.value_chains')->when($this->argument('slug'), fn ($q, $s) => $q->where('slug', $s))->get();
        foreach ($chains as $c) {
            foreach ($validator->validateChain((int) $c->id) as $r) {
                $this->line(sprintf('%s / %s: n=%d R²=%.2f %s', $c->slug, $r['node'], $r['n'], $r['r2'], $r['flagged'] ? '⚠ تعارض' : 'متسق'));
                foreach ($r['inputs'] as $in) {
                    $this->line(sprintf('   %s (ذروة الارتباط بتأخر %d): مرونة تراكمية 0–3 أشهر=%.2f (±%.2f) مقابل وزن %.2f [%.2f–%.2f]%s', $in['from'], $in['lag_months'], $in['beta'], $in['se'], $in['weight'], $in['weight_min'], $in['weight_max'], $in['conflict'] ? ' ⚠' : ''));
                }
            }
        }

        return self::SUCCESS;
    }
}
