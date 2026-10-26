<?php

namespace App\Console;

use App\Core\Api\DataVersion;
use App\Core\Insights\IndicatorCoverage;
use App\Core\Insights\ReadingBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/** يسخّن الحسابات الثقيلة للقراءات وقاموس المؤشرات بعد كل تغيّر في البيانات، فلا ينتظرها أول زائر. */
class WarmInsightsCommand extends Command
{
    protected $signature = 'insights:warm';

    protected $description = 'تسخين تغطية المؤشرات وقراءات الموجز التنفيذي في الذاكرة المؤقتة';

    public function handle(IndicatorCoverage $coverage, ReadingBuilder $readings): int
    {
        $count = count($coverage->all());
        $keys = array_values((array) config('indicator_meta.pulse'));
        Cache::remember('readings:'.DataVersion::token().':'.md5(implode(',', $keys).'|'), 1800, fn () => $readings->build($keys));
        $this->info("✔ التغطية ({$count} مؤشرًا) وقراءات الموجز جاهزة.");

        return self::SUCCESS;
    }
}
