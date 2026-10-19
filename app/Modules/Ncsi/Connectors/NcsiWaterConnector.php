<?php

namespace App\Modules\Ncsi\Connectors;

use App\Core\Validation\Rules\Completeness;
use App\Core\Validation\Rules\Range;

/** المياه (NCSI): إنتاج المياه واستهلاكها شهريًا وحسب القطاع، وعدد التوصيلات وخدمات الصرف الصحي. */
final class NcsiWaterConnector extends KnoemaConnector
{
    protected function knoemaDatasetId(): string
    {
        return 'OMWTR2016';
    }

    protected function datasetKey(): string
    {
        return 'ncsi.water';
    }

    protected function datasetNameAr(): string
    {
        return 'عُمان: المياه';
    }

    protected function datasetNameEn(): string
    {
        return 'Oman water';
    }

    protected function section(): string
    {
        return 'oman';
    }

    protected function intervalMinutes(): int
    {
        return 3 * 24 * 60;
    }

    protected function skipEmptySeries(): bool
    {
        return true;
    }

    protected function series(): array
    {
        $r = fn () => [new Range(0, 1e13, 'reject'), new Completeness(0.1)];

        return [
            new KnoemaSeries('WATER_PROD_M', 'إنتاج المياه (شهري)', 'Water production (monthly)', 'متر مكعب', 'M', 1000010, $r()),
            new KnoemaSeries('WATER_PROD_A', 'إنتاج المياه (سنوي)', 'Water production (annual)', 'متر مكعب', 'A', 1000010, $r()),
            new KnoemaSeries('WATER_CONS_M', 'استهلاك المياه (شهري)', 'Water consumption (monthly)', 'مليون متر مكعب', 'M', 1000020, $r()),
            new KnoemaSeries('WATER_CONS_A', 'استهلاك المياه (سنوي)', 'Water consumption (annual)', 'مليون متر مكعب', 'A', 1000020, $r()),
            new KnoemaSeries('WATER_CONS_GOV', 'استهلاك المياه — حكومي', 'Water consumption — government', 'مليون متر مكعب', 'A', 1000210, $r()),
            new KnoemaSeries('WATER_CONS_RES', 'استهلاك المياه — سكني', 'Water consumption — residential', 'مليون متر مكعب', 'A', 1000220, $r()),
            new KnoemaSeries('WATER_CONS_COM', 'استهلاك المياه — تجاري', 'Water consumption — commercial', 'مليون متر مكعب', 'A', 1000230, $r()),
            new KnoemaSeries('WATER_CONNECTIONS', 'عدد توصيلات المياه', 'Water connections', 'توصيلة', 'A', 1000030, $r()),
            new KnoemaSeries('WATER_SANITATION', 'الوصول إلى خدمات الصرف الصحي المحسنة', 'Access to improved sanitation', '%', 'A', 1000050, $r()),
        ];
    }
}
