<?php

namespace App\Core\Ingestion\Data;

/** سمة وصفية غير رقمية لكيان (حالة، فئة، شكل قانوني، مدينة، تاريخ…). تُحفظ كـ JSON. */
final readonly class ParsedAttribute
{
    public function __construct(
        public string $entityCode,
        public string $key,
        public mixed $value,
    ) {}
}
