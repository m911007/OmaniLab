<?php

namespace App\Core\Ingestion\Data;

use App\Core\Validation\Rule;

final readonly class IndicatorDefinition
{
    /**
     * @param  list<Rule>  $rules  قواعد التحقق الخاصة بهذا المؤشر
     */
    public function __construct(
        public string $key,
        public string $nameAr,
        public string $nameEn,
        public ?string $unit = null,
        public string $frequency = 'A',
        public array $rules = [],
        public ?string $description = null,
    ) {}
}
