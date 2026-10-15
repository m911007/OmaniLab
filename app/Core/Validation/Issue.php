<?php

namespace App\Core\Validation;

final readonly class Issue
{
    /** @param array<string, mixed> $context */
    public function __construct(
        public string $severity, // 'error' يحجز التشغيل كله، 'reject' يستبعد هذه النقطة وحدها، 'warning' يُسجَّل فقط
        public string $rule,
        public string $message,
        public ?string $indicatorKey = null,
        public ?string $entityCode = null,
        public ?string $period = null,
        public array $context = [],
    ) {}

    public function isError(): bool
    {
        return $this->severity === 'error';
    }

    public function isReject(): bool
    {
        return $this->severity === 'reject';
    }
}
