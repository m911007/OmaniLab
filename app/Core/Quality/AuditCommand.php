<?php

namespace App\Core\Quality;

use App\Core\Notifications\AlertDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class AuditCommand extends Command
{
    protected $signature = 'data:audit {--quiet-ok : لا إخراج إن لم توجد مشاكل}';

    protected $description = 'تدقيق سلامة البيانات المعتمدة (تكرار، يتامى، قيم غير صالحة، ركود) وإرسال تنبيه عند الأخطاء';

    public function handle(DataAudit $audit, AlertDispatcher $alerts): int
    {
        $findings = $audit->run();
        Cache::forever('audit:last', [
            'at' => now()->toIso8601String(),
            'findings' => array_map(fn (Finding $f) => (array) $f, $findings),
        ]);

        if ($findings === []) {
            $this->option('quiet-ok') || $this->info('✔ لا مشاكل في سلامة البيانات.');

            return self::SUCCESS;
        }

        foreach ($findings as $f) {
            $this->line(sprintf('[%s] %s — %s (%d)%s', $f->severity, $f->check, $f->message, $f->count, $f->examples ? ' مثل: '.implode(' | ', array_slice($f->examples, 0, 3)) : ''));
        }

        $serious = array_values(array_filter($findings, fn (Finding $f) => $f->severity !== 'info'));
        if ($serious !== []) {
            $alerts->audit($serious);
        }

        return count(array_filter($findings, fn (Finding $f) => $f->severity === 'error')) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
