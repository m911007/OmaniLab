<?php

namespace App\Core\Insights;

/**
 * قراءة مؤشر واحد لصانع القرار: حكم من مراجع صريحة (تاريخ المؤشر نفسه، والخليج، ومرجع موثّق) وجملة مولَّدة بقوالب ثابتة.
 * منطق خالص بلا قاعدة بيانات؛ تغذّيه ReadingBuilder بالأرقام. لا حكم بلا قطبية أو بلا مرجع واحد على الأقل.
 */
final class Reading
{
    public const GOOD = 'good';

    public const NORMAL = 'normal';

    public const ATTENTION = 'attention';

    public const CRITICAL = 'critical';

    public const LABELS = [
        self::GOOD => 'جيد',
        self::NORMAL => 'ضمن المعتاد',
        self::ATTENTION => 'يستدعي الانتباه',
        self::CRITICAL => 'حرج',
    ];

    private const MONTHS = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];

    private const QUARTERS = ['الأول', 'الثاني', 'الثالث', 'الرابع'];

    /**
     * نسبة القراءات السابقة التي تتفوق عليها القراءة الحالية بعد توجيهها بالقطبية (التعادل نصف).
     *
     * @param  list<float>  $past
     */
    public static function historyPercentile(float $latest, array $past, string $polarity): ?float
    {
        if (count($past) < 4) {
            return null;
        }
        $mine = Standing::orient($latest, $polarity);
        $score = 0.0;
        foreach ($past as $v) {
            $o = Standing::orient($v, $polarity);
            $score += $mine > $o ? 1.0 : ($mine === $o ? 0.5 : 0.0);
        }

        return round($score / count($past) * 100, 1);
    }

    /**
     * يجمع المراجع المتاحة في حكم واحد: متوسط موقع المؤشر في تاريخه وبين الخليج.
     *
     * @return array{level: string, label: string, basis: list<string>}|null
     */
    public static function verdict(?float $historyPercentile, ?float $peerPercentile): ?array
    {
        $parts = [];
        $basis = [];
        if ($historyPercentile !== null) {
            $parts[] = $historyPercentile;
            $basis[] = 'history';
        }
        if ($peerPercentile !== null) {
            $parts[] = $peerPercentile;
            $basis[] = 'gcc';
        }
        if ($parts === []) {
            return null;
        }
        $score = array_sum($parts) / count($parts);
        $level = match (true) {
            $score >= 60 => self::GOOD,
            $score >= 35 => self::NORMAL,
            $score >= 15 => self::ATTENTION,
            default => self::CRITICAL,
        };

        return ['level' => $level, 'label' => self::LABELS[$level], 'basis' => $basis];
    }

    /** «2025-08» ← «أغسطس 2025»، «2025-Q3» ← «الربع الثالث 2025»، «2024» كما هي. */
    public static function periodLabel(string $period): string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $period, $m)) {
            return (int) $m[3].' '.self::MONTHS[(int) $m[2] - 1].' '.$m[1];
        }
        if (preg_match('/^(\d{4})-(\d{2})$/', $period, $m)) {
            return self::MONTHS[(int) $m[2] - 1].' '.$m[1];
        }
        if (preg_match('/^(\d{4})-Q([1-4])$/', $period, $m)) {
            return 'الربع '.self::QUARTERS[(int) $m[2] - 1].' '.$m[1];
        }

        return $period;
    }

    public static function number(float $value, int $digits): string
    {
        $text = number_format($value, $digits, '.', ',');

        return $digits > 0 ? rtrim(rtrim($text, '0'), '.') : $text;
    }

    /**
     * الجملة المولَّدة: الرقم والتغيّر، ثم الموقع الخليجي، ثم الموقع في التاريخ. كل جزء يظهر فقط إن توفّرت أرقامه.
     *
     * @param  array{label: string, unit: string, digits: int, polarity: ?string, rate: bool}  $meta
     * @param  array{period: string, value: float}  $latest
     * @param  array{period: string, value: float}|null  $previous
     * @param  array{rank: int, of: int, mean: float}|null  $gcc
     * @param  array{years: int, mean: float, percentile: ?float}|null  $history
     */
    public static function sentence(array $meta, array $latest, ?array $previous, ?array $gcc, ?array $history): string
    {
        $d = $meta['digits'];
        $u = str_starts_with($meta['unit'], '%') ? $meta['unit'] : ' '.$meta['unit'];
        $text = sprintf('بلغ %s %s%s في %s', $meta['label'], self::number($latest['value'], $d), $u, self::periodLabel($latest['period']));

        if ($previous !== null) {
            $diff = $latest['value'] - $previous['value'];
            $direction = abs($diff) < 10 ** -$d / 2 ? 'دون تغيّر يُذكر' : ($diff > 0 ? 'ارتفاعًا' : 'انخفاضًا');
            $text .= sprintf('، %s عن %s%s في %s', $direction, self::number($previous['value'], $d), $u, self::periodLabel($previous['period']));
            if (! $meta['rate'] && $previous['value'] != 0.0 && $direction !== 'دون تغيّر يُذكر') {
                $text .= sprintf(' (%s%s%%)', $diff > 0 ? '+' : '−', self::number(abs($diff / $previous['value'] * 100), 1));
            }
        }
        $text .= '.';

        if ($gcc !== null) {
            $text .= sprintf(' ترتيب عُمان %d من %d خليجيًا (متوسط بقية دول الخليج %s%s).', $gcc['rank'], $gcc['of'], self::number($gcc['mean'], $d), $u);
        }

        if ($history !== null) {
            $relation = $latest['value'] >= $history['mean'] ? 'أعلى من' : 'أدنى من';
            $text .= sprintf(' القراءة %s متوسط آخر %d سنوات (%s%s)', $relation, $history['years'], self::number($history['mean'], $d), $u);
            if ($history['percentile'] !== null && $meta['polarity'] !== null) {
                $text .= $history['percentile'] >= 50
                    ? sprintf('، وأفضل من %s%% من قراءات تلك الفترة', self::number($history['percentile'], 0))
                    : sprintf('، وأسوأ من %s%% من قراءات تلك الفترة', self::number(100 - $history['percentile'], 0));
            }
            $text .= '.';
        }

        return $text;
    }
}
