<?php

namespace App\Modules\Macro\Connectors;

use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Validation\Rules\Range;

/**
 * دعم الوقود الأحفوري (IMF FFS): الدعم الصريح (تحت التكلفة) والضمني (تكاليف بيئية وصحية وازدحام غير مُسعَّرة) كنسبة من الناتج.
 * التقديرات تُنشر بتوقعات حتى 2035، فيُكتفى بما حتى السنة الجارية.
 */
final class ImfFossilSubsidiesConnector extends ImfSdmxConnector
{
    protected function flow(): string
    {
        return 'FFS';
    }

    protected function key(): string
    {
        return '.EX+IM.ALL.POGDP_PT.A';
    }

    protected function codeColumns(): array
    {
        return ['SUB_TYPE'];
    }

    protected function lastYear(): int
    {
        return (int) date('Y');
    }

    protected function datasetKey(): string
    {
        return 'imf.fossil_subsidies';
    }

    protected function datasetNameAr(): string
    {
        return 'دعم الوقود الأحفوري (IMF)';
    }

    protected function datasetNameEn(): string
    {
        return 'Fossil fuel subsidies (IMF)';
    }

    protected function section(): string
    {
        return 'fiscal';
    }

    protected function indicators(): array
    {
        return [
            'EX' => new IndicatorDefinition('imf.ffs_explicit', 'الدعم الصريح للوقود الأحفوري', 'Explicit fossil fuel subsidies', '% من الناتج', 'A', [new Range(0, 100, 'reject')]),
            'IM' => new IndicatorDefinition('imf.ffs_implicit', 'الدعم الضمني للوقود الأحفوري', 'Implicit fossil fuel subsidies', '% من الناتج', 'A', [new Range(0, 100, 'reject')]),
        ];
    }
}
