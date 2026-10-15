<?php

namespace App\Core\Notifications;

use App\Core\Quality\Finding;
use App\Models\Dataset;
use Illuminate\Support\Facades\Cache;

/**
 * نقطة واحدة لكل التنبيهات. يمنع تكرار نفس التنبيه لنفس المجموعة خلال فترة محددة.
 */
final class AlertDispatcher
{
    public function __construct(private readonly TelegramClient $telegram) {}

    public function quarantined(Dataset $dataset, int $errors, string $firstMessage, int $runId): void
    {
        $this->send('quarantined', $dataset, "🚫 <b>بيانات محجوزة</b>\n{$dataset->name_ar}\nفشل التحقق ({$errors} خطأ) — بقيت آخر نسخة سليمة معروضة.\nأول خطأ: ".e($firstMessage)."\nالتشغيل #{$runId}");
    }

    public function failing(Dataset $dataset, int $failures, string $error): void
    {
        $this->send('failing', $dataset, "❌ <b>فشل الجلب</b>\n{$dataset->name_ar}\nإخفاقات متتالية: {$failures}\n".e(mb_substr($error, 0, 300)));
    }

    public function stale(Dataset $dataset): void
    {
        $since = $dataset->last_success_at?->timezone(config('omanilab.display_timezone'))->format('Y-m-d H:i') ?? 'لم ينجح مطلقًا';
        $this->send('stale', $dataset, "⏰ <b>مجموعة متأخرة</b>\n{$dataset->name_ar}\nآخر نجاح: {$since}");
    }

    public function newVersion(Dataset $dataset, ?string $version, int $changed): void
    {
        $this->send('new_version', $dataset, "✅ <b>تحديث جديد</b>\n{$dataset->name_ar}\n".($version ? 'الإصدار: '.e($version)."\n" : '')."نقاط جديدة أو معدّلة: {$changed}", dedupe: false);
    }

    public function recovered(Dataset $dataset): void
    {
        $this->send('recovered', $dataset, "🟢 <b>عادت للعمل</b>\n{$dataset->name_ar}", dedupe: false);
    }

    /** @param  list<Finding>  $findings */
    public function audit(array $findings): void
    {
        $lines = ['🔍 <b>تدقيق سلامة البيانات</b>'];
        foreach ($findings as $f) {
            $icon = $f->severity === 'error' ? '🔴' : '🟡';
            $lines[] = "{$icon} {$f->message} ({$f->count})".($f->examples ? ' — '.e(implode('، ', array_slice($f->examples, 0, 3))) : '');
        }

        // يمنع تكرار نفس التقرير خلال فترة منع التكرار.
        if (Cache::add('alert:audit:'.md5(implode('|', array_map(fn ($f) => $f->check.$f->count, $findings))), 1, now()->addHours((int) config('omanilab.alerts.dedupe_hours')))) {
            $this->telegram->send(implode("\n", $lines));
        }
    }

    public function digest(string $html): void
    {
        $this->telegram->send($html);
    }

    private function send(string $type, Dataset $dataset, string $html, bool $dedupe = true): void
    {
        if ($dedupe) {
            $key = "alert:{$type}:{$dataset->key}";
            if (! Cache::add($key, 1, now()->addHours((int) config('omanilab.alerts.dedupe_hours')))) {
                return;
            }
        }

        $this->telegram->send($html);
    }
}
