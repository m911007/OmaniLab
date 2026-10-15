<?php

namespace App\Core\Ingestion\Data;

/** قياس موجَّه بين كيانين: «المصدر ← الهدف» في فترة (مثل صادرات عُمان إلى الصين في 2023). */
final readonly class ParsedRelation
{
    public function __construct(
        public string $indicatorKey,
        public string $sourceCode,
        public string $targetCode,
        public string $period,
        public float $value,
    ) {}
}
