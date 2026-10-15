<?php

namespace App\Core\Ingestion\Data;

/** قياس واحد بعد التوحيد: (مؤشر، كيان، فترة، قيمة). */
final readonly class ParsedRecord
{
    public function __construct(
        public string $indicatorKey,
        public string $entityCode,
        public string $period,
        public float $value,
    ) {}
}
