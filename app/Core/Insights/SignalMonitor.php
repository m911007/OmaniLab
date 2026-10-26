<?php

namespace App\Core\Insights;

use Illuminate\Support\Facades\DB;

/**
 * يقيّم قواعد config/signals.php على أحدث القراءات المعتمدة ويعيد الإشارات النشطة (المنطلقة في آخر فترة لكل مؤشر).
 * لا يستنتج شيئًا خارج الأرقام: كل إشارة تحمل القيمة والسابقة والفترة.
 */
final class SignalMonitor
{
    /** @return list<array<string, mixed>> */
    public function active(?\DateTimeInterface $now = null): array
    {
        $now ??= now();
        $signals = [];
        foreach ((array) config('signals.rules') as $rule) {
            $signal = $this->evaluate($rule, $now);
            if ($signal !== null) {
                $signals[] = $signal;
            }
        }

        return $signals;
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>|null
     */
    private function evaluate(array $rule, \DateTimeInterface $now): ?array
    {
        $points = DB::table('core.observations as o')
            ->join('core.indicators as i', 'i.id', '=', 'o.indicator_id')->join('core.entities as e', 'e.id', '=', 'o.entity_id')
            ->where('i.key', $rule['indicator'])->where('e.code', $rule['entity'])
            ->orderByDesc('o.period')->limit(14)->get(['o.period', 'o.value'])
            ->map(fn ($r) => ['period' => $r->period, 'value' => (float) $r->value])->all();
        if ($points === []) {
            return null;
        }

        $latest = $points[0];
        if ($this->ageDays($latest['period'], $now) > (int) ($rule['max_age_days'] ?? 90)) {
            return null;
        }

        $threshold = (float) $rule['threshold'];
        $direction = $rule['direction'] ?? 'any';
        $previous = null;
        $change = null;

        switch ($rule['type']) {
            case 'above':
                $hit = $latest['value'] > $threshold;
                break;
            case 'below':
                $hit = $latest['value'] < $threshold;
                break;
            case 'change_pct':
            case 'change_abs':
            case 'yoy_pct':
                $previous = $rule['type'] === 'yoy_pct' ? $this->yearAgo($points, $latest['period']) : ($points[1]['value'] ?? null);
                if ($previous === null || ($rule['type'] !== 'change_abs' && $previous == 0.0)) {
                    return null;
                }
                $change = $rule['type'] === 'change_abs' ? $latest['value'] - $previous : ($latest['value'] - $previous) / abs($previous) * 100;
                $hit = abs($change) >= $threshold && ($direction === 'any' || ($direction === 'up' ? $change > 0 : $change < 0));
                break;
            default:
                return null;
        }
        if (! $hit) {
            return null;
        }

        $fmt = fn (?float $v, int $d = 1) => $v === null ? '—' : rtrim(rtrim(number_format($v, $d, '.', ''), '0'), '.');
        $message = strtr((string) $rule['message'], [
            '{value}' => $fmt($latest['value']), '{previous}' => $fmt($previous), '{change}' => $fmt($change === null ? null : abs($change), $rule['type'] === 'change_abs' ? 2 : 1), '{period}' => $this->periodLabel($latest['period']),
        ]);

        return [
            'id' => $rule['id'], 'title' => $rule['title'], 'message' => $message, 'tone' => $rule['tone'],
            'why' => $rule['why'] ?? null, 'watch' => $rule['watch'] ?? null, 'link' => $rule['link'] ?? null,
            'indicator' => $rule['indicator'], 'entity' => $rule['entity'], 'period' => $latest['period'], 'value' => $latest['value'],
            'previous' => $previous, 'change' => $change,
        ];
    }

    private const MONTHS = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];

    /** تسمية الفترة للقراءة العربية: 2026-09 ← «سبتمبر 2026». */
    private function periodLabel(string $period): string
    {
        return preg_match('/^(\d{4})-(\d{2})$/', $period, $m) && (int) $m[2] >= 1 && (int) $m[2] <= 12 ? self::MONTHS[(int) $m[2] - 1].' '.$m[1] : $period;
    }

    /** @param  list<array{period: string, value: float}>  $points */
    private function yearAgo(array $points, string $period): ?float
    {
        $target = ((int) substr($period, 0, 4) - 1).substr($period, 4);
        foreach ($points as $p) {
            if ($p['period'] === $target) {
                return $p['value'];
            }
        }

        return null;
    }

    /** عمر آخر فترة بالأيام: نهاية الفترة (يوم/شهر/سنة) مقابل الآن. */
    private function ageDays(string $period, \DateTimeInterface $now): int
    {
        $end = match (true) {
            (bool) preg_match('/^\d{4}$/', $period) => new \DateTimeImmutable("{$period}-12-31"),
            (bool) preg_match('/^\d{4}-\d{2}$/', $period) => (new \DateTimeImmutable("{$period}-01"))->modify('last day of this month'),
            (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $period) => new \DateTimeImmutable($period),
            default => new \DateTimeImmutable("{$period}"),
        };

        return (int) floor(($now->getTimestamp() - $end->getTimestamp()) / 86400);
    }
}
