<?php

namespace App\Core\Validation\Rules;

use App\Core\Validation\Issue;
use App\Core\Validation\Rule;
use App\Core\Validation\ValidationContext;

/**
 * المراجعات الطبيعية مسموحة. نقارن الجديد بالمُعتمد سابقًا:
 * نقطة تغيّرت بأكثر من $maxDelta تُسجَّل تحذيرًا، وإن تجاوزت حصة النقاط المتغيرة $errorShare
 * فالأرجح خلل منهجي (وحدة تغيّرت، أعمدة انزاحت) فيُحجز التشغيل.
 */
final class MaxRevision implements Rule
{
    private const MAX_WARNINGS = 25;

    public function __construct(private readonly float $maxDelta, private readonly float $errorShare = 0.25) {}

    public function name(): string
    {
        return 'max_revision';
    }

    /** @return array<string, mixed> */
    public function describe(): array
    {
        return ['max_delta' => $this->maxDelta, 'error_share' => $this->errorShare];
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
            if (abs($r['value'] - $old) > $this->maxDelta) {
                $violations[] = [$r, $old];
            }
        }

        if ($compared === 0 || $violations === []) {
            return;
        }

        $share = count($violations) / $compared;
        if ($share > $this->errorShare) {
            yield new Issue('error', $this->name(), sprintf(
                '%d من %d نقطة (%.0f%%) تغيّرت بأكثر من %s — خلل منهجي مرجّح.',
                count($violations), $compared, $share * 100, $this->maxDelta,
            ), $context->indicatorKey, null, null, ['share' => $share]);

            return;
        }

        foreach (array_slice($violations, 0, self::MAX_WARNINGS) as [$r, $old]) {
            yield new Issue('warning', $this->name(),
                "تغيّرت القيمة من {$old} إلى {$r['value']}.",
                $r['indicator'], $r['entity'], $r['period'], ['old' => $old, 'new' => $r['value']]);
        }
    }
}
