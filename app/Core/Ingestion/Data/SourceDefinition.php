<?php

namespace App\Core\Ingestion\Data;

final readonly class SourceDefinition
{
    public function __construct(
        public string $key,
        public string $nameAr,
        public string $nameEn,
        public ?string $publisher = null,
        public ?string $url = null,
        public ?string $license = null,
        public string $reliability = 'official',
    ) {}
}
