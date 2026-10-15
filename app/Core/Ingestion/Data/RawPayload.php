<?php

namespace App\Core\Ingestion\Data;

final readonly class RawPayload
{
    public string $sha256;

    public function __construct(
        public string $body,
        public string $contentType = 'application/json',
    ) {
        $this->sha256 = hash('sha256', $body);
    }

    public function bytes(): int
    {
        return strlen($this->body);
    }
}
