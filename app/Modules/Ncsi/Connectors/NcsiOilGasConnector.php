<?php

namespace App\Modules\Ncsi\Connectors;

use App\Core\Validation\Rules\Completeness;
use App\Core\Validation\Rules\MaxRevision;
use App\Core\Validation\Rules\Range;
use App\Core\Validation\Rules\RelativeRevision;
use App\Core\Validation\Rules\SeriesScale;
use App\Core\Validation\Rules\SeriesSpike;

/** النفط والغاز في عُمان: سعر الخام وإنتاجه وصادراته وإنتاج الغاز — شهري. */
final class NcsiOilGasConnector extends KnoemaConnector
{
    protected function knoemaDatasetId(): string
    {
        return 'OMOLGS2016';
    }

    protected function datasetKey(): string
    {
        return 'ncsi.oil_gas';
    }

    protected function datasetNameAr(): string
    {
        return 'عُمان: النفط والغاز (شهري)';
    }

    protected function datasetNameEn(): string
    {
        return 'Oman oil and gas (monthly)';
    }

    protected function section(): string
    {
        return 'energy';
    }

    /** 2: استبعاد قفزات الكميات (خطأ خانة عشرية في المصدر). */
    public function revision(): int
    {
        return 2;
    }

    protected function series(): array
    {
        // SeriesSpike(5): كمية شهرية تساوي 5 أضعاف جيرانها خطأ إدخال في المصدر (مثل إنتاج 7/2023 = 10385 بدل 1038.5) فتُستبعد وحدها.
        $volume = fn () => [new Range(0, 1e12), new SeriesScale(100), new SeriesSpike(5.0), new RelativeRevision(0.5, 0.25), new Completeness(0.1)];

        return [
            new KnoemaSeries('OIL_PRICE', 'متوسط سعر النفط العُماني', 'Average Omani crude price', 'دولار/برميل', 'M', 1000080,
                [new Range(0, 300), new MaxRevision(10.0, 0.25), new Completeness(0.1)]),
            new KnoemaSeries('OIL_PRODUCTION', 'متوسط إنتاج النفط الخام اليومي', 'Average daily crude production', 'ألف برميل/يوم', 'M', 1000010, $volume()),
            new KnoemaSeries('OIL_EXPORTS', 'متوسط صادرات النفط الخام اليومية', 'Average daily crude exports', 'ألف برميل/يوم', 'M', 1000060, $volume()),
            new KnoemaSeries('GAS_PRODUCTION', 'إنتاج الغاز الطبيعي', 'Natural gas production', 'مليون م³', 'M', 1000220, $volume()),
        ];
    }
}
