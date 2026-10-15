<?php

namespace App\Core\Validation\Rules;

use App\Core\Validation\Issue;
use App\Core\Validation\Rule;
use App\Core\Validation\ValidationContext;

/**
 * نظير MaxRevision للمستويات (دولار، نسمة…) حيث الفرق المطلق لا يصلح: المراجعة نسبية.
 * نقطة تغيّرت بأكثر من $maxRelative تُسجَّل تحذيرًا؛ وإن تجاوزت حصة المتغيّر $errorShare فخلل منهجي يحجز التشغيل.
 */
final class RelativeRevision implements Rule
{
    private const MAX_WARNINGS = 25;

    public function __construct(
        private readonly float $maxRelative = 0.5,
        private readonly float $errorShare = 0.25,
        private readonly float $absoluteFloor = 0.0,
    ) {}

    public function name(): string
    {
        return 'relative_revision';
    }

    /** @return array<string, mixed> */
    public function describe(): array
    {
        return ['max_relative' => $this->maxRelative, 'error_share' => $this->errorShare];
    }

    public function check(ValidationContext $context): iterable
    {
        $compared = 0;
        $violations = [];
        foreach ($context->records as $r) {
            $old = $context->previous["{$r['entity']}|{$r['period']}"] ?? null;
            if ($old === null) {
                continue;
            }
            $compared++;
            $diff = abs($r['value'] - $old);
            if ($diff > $this->absoluteFloor && $diff > abs($old) * $this->maxRelative) {
                $violations[] = [$r, $old];
            }
        }

        if ($compared === 0 || $violations === []) {
            return;
        }

        $share = count($violations) / $compared;
        if ($share > $this->errorShare) {
            yield new Issue('error', $this->name(), sprintf(
                '%d من %d نقطة (%.0f%%) تغيّرت بأكثر من %.0f%% — خلل منهجي مرجّح.',
                count($violations), $compared, $share * 100, $this->maxRelative * 100,
            ), $context->indicatorKey, null, null, ['share' => $share]);

            return;
        }

        foreach (array_slice($violations, 0, self::MAX_WARNINGS) as [$r, $old]) {
            yield new Issue('warning', $this->name(), "تغيّرت القيمة من {$old} إلى {$r['value']}.",
                $r['indicator'], $r['entity'], $r['period'], ['old' => $old, 'new' => $r['value']]);
        }
    }
}
