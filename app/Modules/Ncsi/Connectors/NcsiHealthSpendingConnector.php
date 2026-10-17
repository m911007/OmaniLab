<?php

namespace App\Modules\Ncsi\Connectors;

use App\Core\Validation\Rules\Completeness;
use App\Core\Validation\Rules\Range;

/** إنفاق وزارة الصحة (المصروفات الإجمالية والرأسمالية والدعم)، بالمليون ريال عُماني، سنويًا من 2020. */
final class NcsiHealthSpendingConnector extends KnoemaConnector
{
    protected function knoemaDatasetId(): string
    {
        return 'fliyxjf';
    }

    protected function datasetKey(): string
    {
        return 'ncsi.health_spending';
    }

    protected function datasetNameAr(): string
    {
        return 'عُمان: إنفاق وزارة الصحة';
    }

    protected function datasetNameEn(): string
    {
        return 'Oman Ministry of Health expenditure';
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
        $rules = fn () => [new Range(0, 1000000.0, 'reject'), new Completeness(0.1)];

        return [
            new KnoemaSeries('HEALTH_MOH_SPENDING', 'إنفاق وزارة الصحة', 'Ministry of Health expenditure', 'مليون ريال عُماني', 'A', 1000000, $rules()),
            new KnoemaSeries('HEALTH_MOH_CAPITAL', 'الإنفاق الرأسمالي لوزارة الصحة', 'MoH capital expenditure', 'مليون ريال عُماني', 'A', 1000010, $rules()),
            new KnoemaSeries('HEALTH_MOH_SUBSIDY', 'إنفاق الدعم لوزارة الصحة', 'MoH subsidy expenditure', 'مليون ريال عُماني', 'A', 1000020, $rules()),
        ];
    }
}
