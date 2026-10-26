<?php

namespace App\Core\Quality;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * نقاط خاطئة في المصدر تُستبعد قبل النشر بسبب موثّق (config/data_exclusions.php)، ونقاط رُوجعت وتبيّن أنها صحيحة.
 * الاستبعاد لا يختلق بديلًا: تختفي القيمة الخاطئة ويبقى سببها في سجل التصحيحات.
 */
final class Exclusions
{
    public const KIND = 'source_error';

    /** @var array<string, string>|null */
    private ?array $index = null;

    public function reason(string $indicatorKey, string $entityCode, string $period): ?string
    {
        if ($this->index === null) {
            $this->index = [];
            foreach ((array) config('data_exclusions.exclude') as $e) {
                $this->index[$e['indicator']."\x1f".$e['entity']."\x1f".$e['period']] = (string) $e['reason'];
            }
        }

        return $this->index === [] ? null : ($this->index[$indicatorKey."\x1f".$entityCode."\x1f".$period] ?? null);
    }

    /** يسجّل النقطة المستبعدة بقيمتها كما نشرها المصدر. */
    public function record(string $indicatorKey, string $entityCode, string $period, float $value, string $reason): void
    {
        try {
            DB::table('ops.corrections')->upsert([[
                'indicator_key' => $indicatorKey, 'entity_code' => $entityCode, 'period' => $period, 'kind' => self::KIND, 'status' => 'excluded',
                'original' => $value, 'corrected' => null, 'note' => $reason, 'detected_at' => now(),
            ]], ['indicator_key', 'entity_code', 'period', 'kind'], ['status', 'original', 'note', 'detected_at']);
            // نقطة رُشّحت انقطاعَ وحدة ثم ثبت أنها خطأ مصدر: لا تبقى «مشتبهًا بها»
            DB::table('ops.corrections')->where(['indicator_key' => $indicatorKey, 'entity_code' => $entityCode, 'period' => $period, 'status' => 'suspected'])->delete();
        } catch (Throwable $e) {
            logger()->warning('[quality] تعذّر تسجيل الاستبعاد: '.$e->getMessage());
        }
    }

    /** يوسم ما رُوجع وثبتت صحته «dismissed» مع السبب، فلا يعود يظهر مشتبهًا به. */
    public function markReviewed(): int
    {
        $n = 0;
        foreach ((array) config('data_exclusions.reviewed') as $r) {
            $n += DB::table('ops.corrections')->where('indicator_key', $r['indicator'])->where('entity_code', $r['entity'])
                ->whereIn('period', $r['periods'])->where('status', 'suspected')
                ->update(['status' => 'dismissed', 'note' => $r['reason']]);
        }

        return $n;
    }
}
