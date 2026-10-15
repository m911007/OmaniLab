<?php

namespace App\Core\Validation\Rules;

use App\Core\Validation\Issue;
use App\Core\Validation\Rule;
use App\Core\Validation\ValidationContext;

/**
 * يكشف تغيّر وحدة القياس لكتلة كاملة من السلسلة (مثل 24 شهرًا بالبرميل بدل ألف برميل): كل نقطة تُقارَن
 * بوسيط السلسلة كلها لا بجيرانها. تُستبعد النقطة المنحرفة (reject) وتبقى بقية السلسلة.
 * للسلاسل المحدودة المدى (شهرية، لكيان واحد)، لا للمستويات التي تنمو آلاف الأضعاف عبر عقود (ناتج دولة منذ 1960).
 */
final class SeriesScale implements Rule
{
    public function __construct(private readonly float $factor = 100.0, private readonly int $minPoints = 6) {}

    public function name(): string
    {
        return 'series_scale';
    }

    /** @return array<string, mixed> */
    public function describe(): array
    {
        return ['factor' => $this->factor];
    }

    public function check(ValidationContext $context): iterable
    {
        $series = [];
        foreach ($context->records as $r) {
            $series[$r['entity']][$r['period']] = $r['value'];
        }

        foreach ($series as $entity => $points) {
            if (count($points) < $this->minPoints) {
                continue;
            }

            $values = array_values($points);
            sort($values);
            $median = $values[intdiv(count($values), 2)];
            if ($median == 0.0) {
                continue;
            }

            foreach ($points as $period => $v) {
                if ($v == 0.0 || ($v > 0) !== ($median > 0)) {
                    continue;
                }
                $ratio = abs($v / $median);
                if ($ratio > $this->factor || $ratio < 1 / $this->factor) {
                    yield new Issue('reject', $this->name(),
                        sprintf('القيمة %s تبعد %s× عن وسيط السلسلة (%s) — تغيّر وحدة قياس مرجّح، استُبعدت النقطة.', $v, round($ratio >= 1 ? $ratio : 1 / $ratio), $median),
                        $context->indicatorKey, (string) $entity, (string) $period, ['value' => $v, 'series_median' => $median]);
                }
            }
        }
    }
}
