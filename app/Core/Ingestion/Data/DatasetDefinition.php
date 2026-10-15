<?php

namespace App\Core\Ingestion\Data;

final readonly class DatasetDefinition
{
    /**
     * @param  list<IndicatorDefinition>  $indicators
     */
    public function __construct(
        public string $key,
        public SourceDefinition $source,
        public string $nameAr,
        public string $nameEn,
        public string $section,
        public int $intervalMinutes,
        public array $indicators,
        public string $reliability = 'official',
    ) {}
}
