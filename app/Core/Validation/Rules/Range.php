<?php

namespace App\Core\Validation\Rules;

use App\Core\Validation\Issue;
use App\Core\Validation\Rule;
use App\Core\Validation\ValidationContext;

/**
 * حدود منطقية للقيمة. تجاوزها خطأ يحجز التشغيل كله افتراضيًا (للمستحيل: بطالة > 100%).
 * للمصادر العالمية الواسعة التي تنشر قيمًا شاذة حقيقية (تضخم مفرط، حصص > 100% بسبب تعريف المصدر) يُستعمل
 * $severity = 'reject' فتُستبعد النقطة وحدها وتبقى موثّقة، ولا تُحجز مئات آلاف النقاط السليمة بسببها.
 */
final class Range implements Rule
{
    public function __construct(private readonly float $min, private readonly float $max, private readonly string $severity = 'error') {}

    public function name(): string
    {
        return 'range';
    }

    /** @return array<string, mixed> */
    public function describe(): array
    {
        return ['min' => $this->min, 'max' => $this->max, 'severity' => $this->severity];
    }

    public function check(ValidationContext $context): iterable
    {
        foreach ($context->records as $r) {
            if ($r['value'] < $this->min || $r['value'] > $this->max) {
                yield new Issue($this->severity, $this->name(),
                    "القيمة {$r['value']} خارج النطاق [{$this->min}, {$this->max}].",
                    $r['indicator'], $r['entity'], $r['period'], ['value' => $r['value']]);
            }
        }
    }
}
