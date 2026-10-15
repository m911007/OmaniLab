<?php

namespace App\Modules\Ncsi\Connectors;

use App\Core\Validation\Rule;

/** سلسلة واحدة من مجموعة بيانات Knoema: عضو المؤشر + أعضاء الأبعاد الثابتة. */
final readonly class KnoemaSeries
{
    /**
     * @param  array<string, int>  $filters  أبعاد يجب تثبيتها بعضو معيّن (وإلا يُختار أول عضو فيه بيانات)
     * @param  list<Rule>  $rules
     */
    public function __construct(
        public string $code,
        public string $nameAr,
        public string $nameEn,
        public ?string $unit,
        public string $frequency,
        public int $indicatorMember,
        public array $rules,
        public array $filters = [],
    ) {}
}
