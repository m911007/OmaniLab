<?php

namespace App\Core\Ingestion\Data;

/** كيان (دولة/إقليم/مجموعة) يصفه المصدر. */
final readonly class ParsedEntity
{
    public function __construct(
        public string $code,
        public string $nameEn,
        public string $type = 'country',
        public ?string $nameAr = null,
    ) {}
}
