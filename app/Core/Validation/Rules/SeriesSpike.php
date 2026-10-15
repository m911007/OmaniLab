<?php

namespace App\Core\Validation\Rules;

use App\Core\Validation\Issue;
use App\Core\Validation\Rule;
use App\Core\Validation\ValidationContext;

/**
 * يكشف خلل وحدة القياس داخل السلسلة نفسها: نقطة تبعد عن وسيط جيرانها بمعامل كبير (مثل 125122 بين 125 و167).
 * تُستبعد النقطة وحدها (reject) ولا تُحجز المجموعة، فالخلل في المصدر لا في بقية بياناته.
 * لا تُطبَّق على النسب المئوية التي تتقلب طبيعيًا؛ تُستخدم للمستويات (دولار، نسمة، عدد…).
 */
final class SeriesSpike implements Rule
{
    public function __construct(
        private readonly float $factor = 50.0,
        private readonly int $window = 3,
        private readonly int $minNeighbours = 3,
    ) {}

    public function name(): string
    {
        return 'series_spike';
    }

    /** @return array<string, mixed> */
    public function describe(): array
    {
        return ['factor' => $this->factor, 'window' => $this->window];
    }

    public function check(ValidationContext $context): iterable
    {
        $series = [];
        foreach ($context->records as $r) {
            $series[$r['entity']][$r['period']] = $r['value'];
        }

        foreach ($series as $entity => $points) {
            ksort($points);
            $periods = array_keys($points);
            $values = array_values($points);

            foreach ($values as $i => $v) {
                $neighbours = [];
                for ($j = max(0, $i - $this->window); $j <= min(count($values) - 1, $i + $this->window); $j++) {
                    if ($j !== $i) {
                        $neighbours[] = $values[$j];
                    }
                }
                if (count($neighbours) < $this->minNeighbours) {
                    continue;
                }

                sort($neighbours);
                $median = $neighbours[intdiv(count($neighbours), 2)];
                if ($median == 0.0 || $v == 0.0 || ($median > 0) !== ($v > 0)) {
                    continue;
                }

                $ratio = abs($v / $median);
                if ($ratio > $this->factor || $ratio < 1 / $this->factor) {
                    yield new Issue('reject', $this->name(),
                        sprintf('القيمة %s تبعد %s× عن وسيط جيرانها (%s) — خلل وحدة قياس مرجّح، استُبعدت النقطة.', $v, round($ratio >= 1 ? $ratio : 1 / $ratio), $median),
                        $context->indicatorKey, (string) $entity, $periods[$i], ['value' => $v, 'neighbour_median' => $median]);
                }
            }
        }
    }
}
