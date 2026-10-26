<?php

namespace App\Console;

use App\Modules\Prices\PriceComparison;
use Illuminate\Console\Attributes\AsCommand;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

#[AsCommand(name: 'prices:watch', description: 'فحص قائمة متابعة الأسعار يوميًا: لقطة سعر لكل منتج متابَع وتعليم ما بلغ السعر المستهدف')]
final class PriceWatchCommand extends Command
{
    protected $signature = 'prices:watch {--limit=200}';

    public function handle(): int
    {
        $watches = DB::table('app.price_watches')->orderByRaw('last_checked_at NULLS FIRST')->limit((int) $this->option('limit'))->get();
        // استعلام واحد لكل (منتج، دول) حتى لو تابعه عدة مستخدمين.
        foreach ($watches->groupBy(fn ($w) => $w->query.'|'.$w->countries) as $key => $group) {
            [$q, $countries] = explode('|', $key);
            try {
                $result = PriceComparison::defaultProviders()->compare($q, explode(',', $countries));
            } catch (Throwable $e) {
                $this->warn("{$q}: {$e->getMessage()}");

                continue;
            }
            PriceComparison::snapshot($q, $result);
            $best = $result['offers'][0] ?? null;
            foreach ($group as $w) {
                $min = $best['omr'] ?? null;
                DB::table('app.price_watches')->where('id', $w->id)->update([
                    'last_min_omr' => $min, 'first_min_omr' => $w->first_min_omr ?? $min, 'last_checked_at' => now(),
                    'last_best' => $best ? json_encode(['title' => $best['title'], 'store' => $best['store'], 'country' => $best['country'], 'omr' => $best['omr'], 'link' => $best['link']], JSON_UNESCAPED_UNICODE) : null,
                    'reached_at' => ($min !== null && $w->target_omr !== null && $min <= (float) $w->target_omr) ? ($w->reached_at ?? now()) : null,
                ]);
            }
            $this->line("{$q} ({$countries}): ".($best ? "{$best['omr']} ر.ع" : 'لا عروض'));
            usleep(500_000);
        }

        return self::SUCCESS;
    }
}
