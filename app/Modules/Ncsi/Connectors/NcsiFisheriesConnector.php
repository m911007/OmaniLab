<?php

namespace App\Modules\Ncsi\Connectors;

use App\Core\Validation\Rules\Completeness;
use App\Core\Validation\Rules\Range;

/** الثروة السمكية (NCSI): كميات الأسماك المنزلة حسب نوع الصيد وصادرات الأسماك وقوارب الصيد. */
final class NcsiFisheriesConnector extends KnoemaConnector
{
    protected function knoemaDatasetId(): string
    {
        return 'OMFSRS2016';
    }

    protected function datasetKey(): string
    {
        return 'ncsi.fisheries';
    }

    protected function datasetNameAr(): string
    {
        return 'عُمان: الثروة السمكية';
    }

    protected function datasetNameEn(): string
    {
        return 'Oman fisheries';
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
            new KnoemaSeries('FISH_LANDED', 'الأسماك المنزلة — الإجمالي (شهري)', 'Fish landings — total', 'طن', 'M', 1000010, $r()),
            new KnoemaSeries('FISH_ARTISANAL', 'الأسماك المنزلة (الصيادون التقليديون، شهري)', 'Artisanal fish landings', 'طن', 'M', 1000020, $r()),
            new KnoemaSeries('FISH_COMMERCIAL', 'الأسماك المنزلة (الصيد التجاري، شهري)', 'Commercial fish landings', 'طن', 'M', 1000030, $r()),
            new KnoemaSeries('FISH_AQUACULTURE', 'الاستزراع السمكي', 'Aquaculture', 'طن', 'A', 1000050, $r()),
        ];
    }
}
