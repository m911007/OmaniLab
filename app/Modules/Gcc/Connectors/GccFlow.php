<?php

namespace App\Modules\Gcc\Connectors;

use App\Core\Validation\Rule;
use App\Core\Validation\Rules\Completeness;
use App\Core\Validation\Rules\MaxRevision;
use App\Core\Validation\Rules\Range;
use App\Core\Validation\Rules\RelativeRevision;
use App\Core\Validation\Rules\SeriesSpike;

/** تدفق بيانات واحد من GCC-Stat (dataflow في سجل SDMX). */
final readonly class GccFlow
{
    /** @param  list<Rule>  $rules */
    public function __construct(
        public string $flowId,
        public string $code,
        public string $nameAr,
        public string $nameEn,
        public ?string $unit,
        public string $frequency,
        public array $rules,
    ) {}

    /** مستوى (دولار، نسمة، عدد…): يُكشف خلل الوحدة ضمن السلسلة وتُراقَب المراجعات نسبيًا. */
    public static function level(string $flowId, string $code, string $nameAr, string $nameEn, ?string $unit, string $frequency = 'A'): self
    {
        return new self($flowId, $code, $nameAr, $nameEn, $unit, $frequency, [
            new Range(0, 1e15), new SeriesSpike(50), new RelativeRevision(0.5, 0.25), new Completeness(0.1),
        ]);
    }

    /** نسبة مئوية: مراجعات مطلقة. */
    public static function percent(string $flowId, string $code, string $nameAr, string $nameEn, string $frequency = 'A', float $min = -100, float $max = 1000): self
    {
        return new self($flowId, $code, $nameAr, $nameEn, '%', $frequency, [
            new Range($min, $max), new MaxRevision(10.0, 0.25), new Completeness(0.1),
        ]);
    }
}
