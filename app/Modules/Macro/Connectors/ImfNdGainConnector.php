<?php

namespace App\Modules\Macro\Connectors;

use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Validation\Rules\Range;

/** مؤشر نوتردام للتكيف المناخي ND-GAIN (نسخة صندوق النقد): الهشاشة والجاهزية بين 0 و1. */
final class ImfNdGainConnector extends ImfSdmxConnector
{
    protected function flow(): string
    {
        return 'NDGAIN';
    }

    protected function key(): string
    {
        return '.NDGAIN_IX+RS_NUM+VSC_NUM.A';
    }

    protected function codeColumns(): array
    {
        return ['INDICATOR'];
    }

    protected function datasetKey(): string
    {
        return 'imf.ndgain';
    }

    protected function datasetNameAr(): string
    {
        return 'القدرة على التكيف المناخي (ND-GAIN)';
    }

    protected function datasetNameEn(): string
    {
        return 'Climate adaptation readiness (ND-GAIN)';
    }

    protected function section(): string
    {
        return 'environment';
    }

    protected function indicators(): array
    {
        $rules = [new Range(0, 1, 'reject')];

        return [
            'NDGAIN_IX' => new IndicatorDefinition('imf.ndgain_index', 'مؤشر ND-GAIN (الأعلى = أفضل)', 'ND-GAIN index (higher = better)', '0–1', 'A', $rules),
            'RS_NUM' => new IndicatorDefinition('imf.ndgain_readiness', 'الجاهزية للتكيف المناخي', 'Climate readiness', '0–1', 'A', $rules),
            'VSC_NUM' => new IndicatorDefinition('imf.ndgain_vulnerability', 'الهشاشة المناخية (الأعلى = أسوأ)', 'Climate vulnerability (higher = worse)', '0–1', 'A', $rules),
        ];
    }
}
