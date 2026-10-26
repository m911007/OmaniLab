<?php

namespace App\Core\Quality;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * كشف انقطاع الوحدة في سلسلة: مصدر يغيّر وحدة النشر (مليون ← مليار) دون تغيير وصفها، فتهبط القيم نحو ألف ضعف.
 * نقطة تصغر عن متوسط ما قبلها بنحو ألف ضعف (بين 500 و2000) — وتتبعها نقطة على المستوى الجديد نفسه، أو تكون الأخيرة —
 * تُعاد إلى وحدة السلسلة ×1000 ويُسجَّل التصحيح في ops.corrections. شرط الاستمرار يمنع تصحيح قيمة اقتربت من الصفر فعلًا.
 */
final class UnitShift
{
    public const KIND = 'unit_shift_x1000';

    private const LOW = 500.0;

    private const HIGH = 2000.0;

    /**
     * @param  array<string, float>  $points  فترة => قيمة
     * @return array<string, float>
     */
    public static function fix(array $points, string $indicatorKey, string $entityCode, bool $record = true): array
    {
        ksort($points);
        $periods = array_map('strval', array_keys($points));
        $history = [];
        $fixes = [];
        foreach ($periods as $i => $p) {
            $v = $points[$p];
            if (count($history) >= 4 && $v > 0) {
                $recent = array_slice($history, -4);
                $base = array_sum($recent) / 4;
                $next = isset($periods[$i + 1]) ? $points[$periods[$i + 1]] : null;
                $persists = $next === null || ($next > 0 && self::inBand($base / $next));
                if (self::inBand($base / $v) && $persists) {
                    $fixes[] = ['period' => $p, 'original' => $v, 'corrected' => $v * 1000];
                    $v *= 1000;
                    $points[$p] = $v;
                }
            }
            $history[] = $v;
        }
        if ($fixes !== [] && $record) {
            self::record($indicatorKey, $entityCode, $fixes);
        }

        return $points;
    }

    private static function inBand(float $ratio): bool
    {
        return $ratio > self::LOW && $ratio < self::HIGH;
    }

    /** @param  list<array{period: string, original: float, corrected: float}>  $fixes */
    private static function record(string $indicatorKey, string $entityCode, array $fixes): void
    {
        logger()->warning(sprintf('[quality] %s/%s: صُحّحت %d نقطة نُشرت بالمليار بدل المليون (×1000).', $indicatorKey, $entityCode, count($fixes)));
        try {
            DB::table('ops.corrections')->upsert(array_map(fn (array $f) => [
                'indicator_key' => $indicatorKey, 'entity_code' => $entityCode, 'period' => $f['period'], 'kind' => self::KIND, 'status' => 'applied',
                'original' => $f['original'], 'corrected' => $f['corrected'],
                'note' => 'نشرها المصدر بوحدة أكبر بألف ضعف من بقية السلسلة؛ أُعيدت إلى وحدة السلسلة.', 'detected_at' => now(),
            ], $fixes), ['indicator_key', 'entity_code', 'period', 'kind'], ['status', 'original', 'corrected', 'note', 'detected_at']);
        } catch (Throwable $e) {
            // التصحيح نفسه لا يتوقف إن تعذّر التسجيل (مثل اتصال بدور قراءة فقط)
            logger()->warning('[quality] تعذّر تسجيل التصحيح: '.$e->getMessage());
        }
    }
}
