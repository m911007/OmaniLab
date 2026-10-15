<?php

namespace App\Modules\Macro\Connectors;

use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Validation\Rules\Completeness;
use App\Core\Validation\Rules\Range;

/** كفاية الاحتياطيات الدولية (Assessing Reserve Adequacy) لصندوق النقد. */
final class ImfReservesConnector extends ImfDataMapperConnector
{
    protected function datasetKey(): string
    {
        return 'imf.reserves';
    }

    protected function datasetNameAr(): string
    {
        return 'كفاية الاحتياطيات الدولية (IMF ARA)';
    }

    protected function datasetNameEn(): string
    {
        return 'IMF reserve adequacy (ARA)';
    }

    protected function section(): string
    {
        return 'finance';
    }

    /** @return list<IndicatorDefinition> */
    protected function indicators(): array
    {
        $ratio = fn () => [new Range(0, 5_000, 'reject'), new Completeness(0.2)];

        return [
            new IndicatorDefinition('imf.Reserves_ARA', 'الاحتياطيات إلى مقياس ARA', 'Reserves / ARA metric', 'نسبة', rules: $ratio()),
            new IndicatorDefinition('imf.Reserves_M2', 'الاحتياطيات إلى النقد الواسع', 'Reserves / broad money', 'نسبة', rules: $ratio()),
            new IndicatorDefinition('imf.Reserves_STD', 'الاحتياطيات إلى الدين قصير الأجل', 'Reserves / short-term debt', 'نسبة', rules: $ratio()),
            new IndicatorDefinition('imf.Reserves_M', 'الاحتياطيات إلى واردات شهر', 'Reserves / (imports/12)', 'أشهر', rules: $ratio()),
        ];
    }
}
