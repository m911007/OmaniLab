<?php

namespace App\Core\Quality;

final readonly class Finding
{
    /** @param  list<string>  $examples */
    public function __construct(
        public string $severity, // error | warning | info
        public string $check,
        public string $message,
        public int $count = 0,
        public array $examples = [],
    ) {}
}
