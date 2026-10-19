<?php

namespace App\Modules\Ncsi\Connectors;

use App\Core\Validation\Rules\Completeness;
use App\Core\Validation\Rules\Range;

/** الإسكان والأراضي (NCSI): القطع المخططة والممنوحة والمسجلة وإباحات البناء. */
final class NcsiHousingLandConnector extends KnoemaConnector
{
    protected function knoemaDatasetId(): string
    {
        return 'OMHSNG2016';
    }

    protected function datasetKey(): string
    {
        return 'ncsi.housing_land';
    }

    protected function datasetNameAr(): string
    {
        return 'عُمان: الأراضي والإسكان';
    }

    protected function datasetNameEn(): string
    {
        return 'Oman land and housing';
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
            new KnoemaSeries('LAND_PLANNED', 'قطع الأراضي المخططة', 'Planned land plots', 'قطعة', 'A', 1000000, $r()),
            new KnoemaSeries('LAND_GRANTED', 'قطع الأراضي الممنوحة', 'Land plots granted', 'قطعة', 'A', 1000010, $r()),
            new KnoemaSeries('LAND_REGISTERED', 'القطع المسجلة لأول مرة', 'Plots registered first time', 'قطعة', 'A', 1000020, $r()),
            new KnoemaSeries('BUILDING_PERMITS', 'إباحات البناء', 'Building permits', 'إباحة', 'A', 1000040, $r()),
        ];
    }
}
