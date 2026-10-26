<?php

namespace App\Console;

use App\Modules\MarketIntel\OecTradeClient;
use Illuminate\Console\Attributes\AsCommand;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

#[AsCommand(name: 'chains:trade', description: 'حساب الدول المصدّرة وحصصها وتركّزها (HHI) لكل عقدة في سلاسل القيمة من BACI عبر OEC')]
final class ChainTradeCommand extends Command
{
    protected $signature = 'chains:trade';

    public function handle(OecTradeClient $oec): int
    {
        $products = collect($oec->products())->keyBy('hs4');
        $year = $oec->latestYear();
        $names = DB::table('core.entities')->pluck('name_ar', 'code');
        foreach (DB::table('intel.chain_nodes')->get() as $node) {
            $codes = array_filter(explode(',', trim((string) $node->hs_codes, '{}')));
            $hs4 = $codes ? substr(trim($codes[0], '"'), 0, 4) : null;
            $product = $hs4 ? $products->get($hs4) : null;
            if (! $product) {
                continue;
            }
            try {
                $rows = collect($oec->trade((int) $product['id'], 'Exporter Country', [$year]))->where('year', $year)->sortByDesc('value')->values();
            } catch (Throwable $e) {
                $this->warn("{$node->slug}: {$e->getMessage()}");

                continue;
            }
            $total = (float) $rows->sum('value');
            if ($total <= 0) {
                continue;
            }
            $hhi = $rows->sum(fn ($r) => ($r['value'] / $total * 100) ** 2);
            $top = $rows->take(10)->map(fn ($r) => ['iso' => $r['iso'], 'name_ar' => $names[$r['iso']] ?? $r['name'], 'value_usd' => round($r['value']), 'share' => round($r['value'] / $total, 4)])->all();
            DB::table('intel.node_trade')->updateOrInsert(['node_id' => $node->id], ['hs4' => $hs4, 'year' => $year, 'total_usd' => $total, 'hhi' => round($hhi), 'exporters' => json_encode($top, JSON_UNESCAPED_UNICODE), 'computed_at' => now()]);
            $this->line("{$node->slug} (HS {$hs4}): HHI ".round($hhi).'، أكبر مصدّر '.($top[0]['name_ar'] ?? '-'));
        }

        return self::SUCCESS;
    }
}
