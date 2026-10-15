<?php

namespace App\Core\Validation\Rules;

use App\Core\Validation\Issue;
use App\Core\Validation\Rule;
use App\Core\Validation\ValidationContext;

/** عدد النقاط الجديد لا ينقص عن المُعتمد سابقًا بأكثر من $tolerance (لا تختفي دول أو سنوات فجأة). */
final class Completeness implements Rule
{
    public function __construct(private readonly float $tolerance = 0.1) {}

    public function name(): string
    {
        return 'completeness';
    }

    /** @return array<string, mixed> */
    public function describe(): array
    {
        return ['tolerance' => $this->tolerance];
    }

    public function check(ValidationContext $context): iterable
    {
        if ($context->previousCount === 0) {
            return;
        }

        $incoming = count($context->records);
        $floor = (int) floor($context->previousCount * (1 - $this->tolerance));
        if ($incoming < $floor) {
            yield new Issue('error', $this->name(),
                "عدد النقاط الوارد {$incoming} أقل من المُعتمد {$context->previousCount} بأكثر من ".($this->tolerance * 100).'%.',
                $context->indicatorKey, null, null, ['incoming' => $incoming, 'previous' => $context->previousCount]);
        }
    }
}
