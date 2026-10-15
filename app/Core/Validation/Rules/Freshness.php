<?php

namespace App\Core\Validation\Rules;

use App\Core\Validation\Issue;
use App\Core\Validation\Rule;
use App\Core\Validation\ValidationContext;

/** مستوى المجموعة: لا يُقبل إصدار أقدم من المُعتمد حاليًا. */
final class Freshness implements Rule
{
    public function name(): string
    {
        return 'freshness';
    }

    /** @return array<string, mixed> */
    public function describe(): array
    {
        return [];
    }

    public function check(ValidationContext $context): iterable
    {
        if ($context->incomingPublishedAt && $context->currentPublishedAt
            && $context->incomingPublishedAt->lt($context->currentPublishedAt)) {
            yield new Issue('error', $this->name(), sprintf(
                'الإصدار الوارد (%s) أقدم من المعتمد حاليًا (%s).',
                $context->incomingPublishedAt->toDateString(),
                $context->currentPublishedAt->toDateString(),
            ));
        }
    }
}
