<?php

namespace App\Modules\WorldBank\Connectors;

use App\Core\Validation\Rule;
use App\Core\Validation\Rules\Completeness;
use App\Core\Validation\Rules\MaxRevision;
use App\Core\Validation\Rules\Range;
use App\Core\Validation\Rules\RelativeRevision;
use App\Core\Validation\Rules\SeriesSpike;

/** مؤشر واحد من مؤشرات التنمية العالمية (WDI). المفتاح الداخلي: wb.<الرمز>. */
final readonly class WbIndicator
{
    /** @param  list<Rule>  $rules */
    public function __construct(
        public string $code,
        public string $nameAr,
        public string $nameEn,
        public ?string $unit,
        public array $rules,
    ) {}

    /**
     * نسبة تقع عادةً بين 0 و100 (حصص، معدلات). تتجاوزها بعض القيم الحقيقية في المصدر لأسباب تعريفية (صادرات خدمة > 100%)
     * فلا نفرض 100 صلبة، بل نستبعد المستحيل الصريح فقط.
     */
    public static function share(string $code, string $nameAr, string $nameEn, string $unit = '%'): self
    {
        return new self($code, $nameAr, $nameEn, $unit, [new Range(-1000, 1000, 'reject'), new MaxRevision(10.0, 0.25), new Completeness(0.2)]);
    }

    /** نسبة قد تتجاوز 100 أو تكون سالبة (% من الناتج، نمو…). */
    public static function ratio(string $code, string $nameAr, string $nameEn, string $unit = '% من الناتج المحلي'): self
    {
        return new self($code, $nameAr, $nameEn, $unit, [new Range(-1e6, 1e8, 'reject'), new MaxRevision(15.0, 0.25), new Completeness(0.2)]);
    }

    /** مستوى موجب (دولار، نسمة…): خلل الوحدة ضمن السلسلة يُستبعد، والمراجعات نسبية. */
    public static function level(string $code, string $nameAr, string $nameEn, string $unit): self
    {
        return new self($code, $nameAr, $nameEn, $unit, [new Range(0, 1e16, 'reject'), new SeriesSpike(50), new RelativeRevision(0.5, 0.25), new Completeness(0.2)]);
    }

    /** قيمة عددية عامة بحد أقصى معقول (سنوات، أعداد لكل 100…). لا SeriesSpike لأنها قد تقفز حقًا (إعادة تقييم عملة). */
    public static function value(string $code, string $nameAr, string $nameEn, ?string $unit, float $max = 1e9): self
    {
        return new self($code, $nameAr, $nameEn, $unit, [new Range(0, $max, 'reject'), new RelativeRevision(0.5, 0.25), new Completeness(0.2)]);
    }
}
