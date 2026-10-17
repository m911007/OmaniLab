<?php

namespace App\Modules\Ncsi\Connectors;

use App\Core\Validation\Rules\Completeness;
use App\Core\Validation\Rules\Range;

/** مؤشر الأمن الغذائي الوطني (0–1) ومكوّناته: التوافر والإتاحة والقدرة على التحمّل والاستخدام، سنويًا منذ 2000. */
final class NcsiFoodSecurityConnector extends KnoemaConnector
{
    protected function knoemaDatasetId(): string
    {
        return 'glahxrb';
    }

    protected function datasetKey(): string
    {
        return 'ncsi.food_security';
    }

    protected function datasetNameAr(): string
    {
        return 'عُمان: مؤشر الأمن الغذائي';
    }

    protected function datasetNameEn(): string
    {
        return 'Oman food security index';
    }

    protected function section(): string
    {
        return 'society';
    }

    protected function intervalMinutes(): int
    {
        return 24 * 60;
    }

    protected function series(): array
    {
        $rules = fn () => [new Range(0, 1, 'reject'), new Completeness(0.1)];

        return [
            new KnoemaSeries('FOOD_SECURITY_INDEX', 'مؤشر الأمن الغذائي', 'Food security index', '0–1', 'A', 1000000, $rules()),
            new KnoemaSeries('FOOD_AVAILABILITY', 'التوافر الغذائي', 'Food availability', '0–1', 'A', 1000010, $rules()),
            new KnoemaSeries('FOOD_ACCESSIBILITY', 'إتاحة الغذاء', 'Food accessibility', '0–1', 'A', 1000020, $rules()),
            new KnoemaSeries('FOOD_AFFORDABILITY', 'القدرة على تحمّل كلفة الغذاء', 'Food affordability', '0–1', 'A', 1000030, $rules()),
            new KnoemaSeries('FOOD_UTILIZATION', 'استخدام الغذاء', 'Food utilization', '0–1', 'A', 1000040, $rules()),
        ];
    }
}
