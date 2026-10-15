<?php

namespace App\Modules\Ncsi\Connectors;

use App\Core\Validation\Rules\Completeness;
use App\Core\Validation\Rules\MaxRevision;
use App\Core\Validation\Rules\Range;

/** الرقم القياسي لأسعار المستهلك في عُمان (سنة الأساس 2018) — شهري. */
final class NcsiPricesConnector extends KnoemaConnector
{
    protected function knoemaDatasetId(): string
    {
        return 'rauaefb';
    }

    protected function datasetKey(): string
    {
        return 'ncsi.prices';
    }

    protected function datasetNameAr(): string
    {
        return 'عُمان: الرقم القياسي لأسعار المستهلك والتضخم (شهري)';
    }

    protected function datasetNameEn(): string
    {
        return 'Oman CPI and inflation (monthly)';
    }

    protected function section(): string
    {
        return 'oman';
    }

    protected function series(): array
    {
        return [
            new KnoemaSeries('CPI_INDEX', 'الرقم القياسي لأسعار المستهلك (2018=100)', 'Consumer price index (2018=100)', 'نقطة', 'M', 1000050,
                [new Range(0, 1000), new MaxRevision(5.0, 0.25), new Completeness(0.1)]),
            new KnoemaSeries('CPI_INFLATION', 'معدل التضخم السنوي', 'Inflation rate (y/y)', '%', 'M', 1000000,
                [new Range(-50, 100), new MaxRevision(2.0, 0.25), new Completeness(0.1)]),
        ];
    }
}
