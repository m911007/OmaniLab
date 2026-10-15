<?php

namespace App\Core\Validation;

use Carbon\CarbonImmutable;

/**
 * ما تراه القاعدة: السجلات الجديدة لمؤشر واحد (أو لكل المجموعة إن لم يُحدَّد مؤشر)
 * مع القيم المعتمدة سابقًا للمقارنة.
 */
final readonly class ValidationContext
{
    /**
     * @param  list<array{indicator: string, entity: string, period: string, value: float}>  $records
     * @param  array<string, float>  $previous  مفتاحه "entity|period" ← القيمة المعتمدة سابقًا
     * @param  list<string>  $knownIndicatorKeys
     */
    public function __construct(
        public ?string $indicatorKey,
        public array $records,
        public array $previous = [],
        public int $previousCount = 0,
        public array $knownIndicatorKeys = [],
        public ?CarbonImmutable $incomingPublishedAt = null,
        public ?CarbonImmutable $currentPublishedAt = null,
        /** كيانات وسمات مُجلوبة (لمجموعات وصفية بلا قيم رقمية) */
        public int $descriptiveCount = 0,
    ) {}
}
