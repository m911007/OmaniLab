<?php

namespace App\Core\Ingestion\Data;

use Carbon\CarbonImmutable;

/**
 * نتيجة الفحص الرخيص: هل تغيّر المصدر؟ الـ token بصمة تُقارَن بآخر بصمة مُرحَّلة.
 * مصدر بلا فحص رخيص يعيد token = null، فيُجلب دائمًا ويُقارَن بصمة الحمولة نفسها.
 */
final readonly class ProbeResult
{
    public function __construct(
        public ?string $token,
        public ?string $version = null,
        public ?CarbonImmutable $publishedAt = null,
    ) {}
}
