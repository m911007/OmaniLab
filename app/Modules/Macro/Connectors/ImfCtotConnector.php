<?php

namespace App\Modules\Macro\Connectors;

use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Validation\Rules\Range;

/**
 * معدل التبادل التجاري للسلع (IMF CTOT): مؤشر يتتبع تأثير تحرك أسعار السلع العالمية على القوة الشرائية لصادرات الدولة
 * مقابل وارداتها (صافي الصادرات، أوزان تاريخية ثابتة). ارتفاعه = تحسّن؛ لدول النفط يلخص أثر دورة أسعار الطاقة في سطر واحد.
 */
final class ImfCtotConnector extends ImfSdmxConnector
{
    protected function flow(): string
    {
        return 'CTOT';
    }

    protected function key(): string
    {
        return '.CEMPI_CTOTNX_TT.H_FW_IX.A';
    }

    /** @return list<string> */
    protected function codeColumns(): array
    {
        return ['INDICATOR'];
    }

    protected function datasetKey(): string
    {
        return 'imf.ctot';
    }

    protected function datasetNameAr(): string
    {
        return 'معدل التبادل التجاري للسلع (IMF CTOT)';
    }

    protected function datasetNameEn(): string
    {
        return 'Commodity terms of trade (IMF CTOT)';
    }

    protected function section(): string
    {
        return 'trade';
    }

    protected function lastYear(): int
    {
        return (int) date('Y');
    }

    protected function indicators(): array
    {
        return [
            'CEMPI_CTOTNX_TT' => new IndicatorDefinition('imf.ctot_net_exports', 'معدل التبادل التجاري للسلع (صافي الصادرات)', 'Commodity terms of trade, net exports', 'مؤشر', 'A', [new Range(0, 5000, 'reject')]),
        ];
    }
}
