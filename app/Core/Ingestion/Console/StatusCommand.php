<?php

namespace App\Core\Ingestion\Console;

use App\Models\Dataset;
use Illuminate\Console\Command;

class StatusCommand extends Command
{
    protected $signature = 'datasets:status';

    protected $description = 'حالة كل المجموعات وتواريخ آخر فحص وآخر تغيير';

    public function handle(): int
    {
        $tz = config('omanilab.display_timezone');
        $fmt = fn ($d) => $d?->timezone($tz)->format('Y-m-d H:i') ?? '—';

        $this->table(
            ['المفتاح', 'الحالة', 'الإصدار', 'آخر فحص', 'آخر تغيير', 'الفحص القادم', 'إخفاقات'],
            Dataset::orderBy('key')->get()->map(fn (Dataset $d) => [
                $d->key, $d->status->value, $d->source_version ?? '—', $fmt($d->last_checked_at),
                $fmt($d->last_changed_at), $fmt($d->next_check_at), $d->consecutive_failures,
            ])->all(),
        );

        return self::SUCCESS;
    }
}
