<?php

namespace App\Core\Insights;

/**
 * موقع دولة في مؤشر: تدرّج الجودة (نسبة الدول التي تتفوق عليها بعد توجيه المؤشر حسب قطبيته)، والمرتبة، والاتجاه، والتصنيف.
 * منطق خالص بلا قاعدة بيانات: يُختبر وحده، وتغذّيه ScorecardBuilder بالأرقام.
 */
final class Standing
{
    public const STRENGTH = 'strength';

    public const ERODING = 'eroding';

    public const CHALLENGE = 'challenge';

    public const RECOVERING = 'recovering';

    public const OPPORTUNITY = 'opportunity';

    public const WATCH = 'watch';

    public const NEUTRAL = 'neutral';

    public const STRONG_FROM = 70.0;

    public const WEAK_UNTIL = 30.0;

    /** تغيّر موجَّه أقل من هذا الجزء من الانحراف المعياري بين الدول يُعدّ ثباتًا. */
    public const TREND_DEADBAND = 0.1;

    /** يحوّل قيمة إلى مقياس "الأعلى أفضل" وفق القطبية. */
    public static function orient(float $value, string $polarity): float
    {
        if ($polarity === 'lower') {
            return -$value;
        }
        if (str_starts_with($polarity, 'target:')) {
            return -abs($value - (float) substr($polarity, 7));
        }

        return $value;
    }

    /**
     * @param  array<string, float>  $values  رمز الدولة => القيمة الخام لكل الدول المقارَنة (تتضمن الدولة المرصودة)
     * @return array{rank: int, of: int, percentile: float}|null
     */
    public static function position(string $entity, array $values, string $polarity): ?array
    {
        if (! isset($values[$entity]) || count($values) < 2) {
            return null;
        }
        $mine = self::orient($values[$entity], $polarity);
        $better = 0;
        $worse = 0;
        $ties = 0;
        foreach ($values as $code => $v) {
            if ($code === $entity) {
                continue;
            }
            $o = self::orient($v, $polarity);
            if ($o > $mine) {
                $better++;
            } elseif ($o < $mine) {
                $worse++;
            } else {
                $ties++;
            }
        }
        $others = count($values) - 1;

        return ['rank' => $better + 1, 'of' => count($values), 'percentile' => round(($worse + 0.5 * $ties) / $others * 100, 1)];
    }

    /**
     * @param  array<int, float>  $values  القيم الخام عبر الدول (لقياس تشتتها)
     * @return 'improving'|'worsening'|'stable'|null
     */
    public static function trend(?float $latest, ?float $baseline, array $values, string $polarity): ?string
    {
        if ($latest === null || $baseline === null || count($values) < 3) {
            return null;
        }
        $oriented = array_map(fn (float $v) => self::orient($v, $polarity), $values);
        $mean = array_sum($oriented) / count($oriented);
        $sd = sqrt(array_sum(array_map(fn (float $v) => ($v - $mean) ** 2, $oriented)) / count($oriented));
        if ($sd <= 0.0) {
            return 'stable';
        }
        $z = (self::orient($latest, $polarity) - self::orient($baseline, $polarity)) / $sd;

        return $z > self::TREND_DEADBAND ? 'improving' : ($z < -self::TREND_DEADBAND ? 'worsening' : 'stable');
    }

    public static function classify(float $percentile, ?string $trend): string
    {
        if ($percentile >= self::STRONG_FROM) {
            return $trend === 'worsening' ? self::ERODING : self::STRENGTH;
        }
        if ($percentile <= self::WEAK_UNTIL) {
            return $trend === 'improving' ? self::RECOVERING : self::CHALLENGE;
        }

        return match ($trend) {
            'improving' => self::OPPORTUNITY,
            'worsening' => self::WATCH,
            default => self::NEUTRAL,
        };
    }
}
