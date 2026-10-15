<?php

namespace App\Core\Validation\Rules;

use App\Core\Validation\Issue;
use App\Core\Validation\Rule;
use App\Core\Validation\ValidationContext;

/** مستوى المجموعة: الحمولة غير فارغة، والصيغ سليمة، ولا مؤشرات غير معرّفة. */
final class WellFormed implements Rule
{
    private const PERIOD = '/^\d{4}(-(Q[1-4]|\d{2}(-\d{2})?))?$/';

    /** كيان، أو زوج «مصدر>هدف» للعلاقات الثنائية. */
    private const ENTITY = '/^[A-Za-z0-9_.-]{1,64}(>[A-Za-z0-9_.-]{1,64})?$/';

    public function name(): string
    {
        return 'well_formed';
    }

    /** @return array<string, mixed> */
    public function describe(): array
    {
        return [];
    }

    public function check(ValidationContext $context): iterable
    {
        if ($context->records === [] && $context->descriptiveCount === 0) {
            yield new Issue('error', $this->name(), 'الحمولة لا تحوي أي سجل أو كيان أو سمة بعد التحليل (قد يكون المصدر غيّر شكله).');

            return;
        }

        $unknown = [];
        foreach ($context->records as $r) {
            if (! preg_match(self::PERIOD, $r['period'])) {
                yield new Issue('error', $this->name(), "صيغة فترة غير صالحة: {$r['period']}", $r['indicator'], $r['entity'], $r['period']);
            }
            if (! preg_match(self::ENTITY, $r['entity'])) {
                yield new Issue('error', $this->name(), "رمز كيان غير صالح: {$r['entity']}", $r['indicator'], $r['entity'], $r['period']);
            }
            if (! is_finite($r['value'])) {
                yield new Issue('error', $this->name(), 'قيمة غير منتهية.', $r['indicator'], $r['entity'], $r['period']);
            }
            if (! in_array($r['indicator'], $context->knownIndicatorKeys, true)) {
                $unknown[$r['indicator']] = true;
            }
        }

        foreach (array_keys($unknown) as $key) {
            yield new Issue('error', $this->name(), "مؤشر غير معرّف في تعريف المجموعة: {$key}", $key);
        }
    }
}
