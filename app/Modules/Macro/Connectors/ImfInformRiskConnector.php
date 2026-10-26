<?php

namespace App\Modules\Macro\Connectors;

use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Validation\Rules\Range;

/**
 * مؤشر INFORM للمخاطر (المفوضية الأوروبية والأمم المتحدة عبر صندوق النقد): احتمال أزمة إنسانية أو كارثة تتجاوز قدرة الدولة، من 0 (الأقل خطرًا) إلى 10،
 * وهو مركّب من التعرض للمخاطر الطبيعية والبشرية، وهشاشة السكان، ونقص القدرة على المواجهة. سنوي منذ 2013 لنحو 190 دولة.
 */
final class ImfInformRiskConnector extends ImfSdmxConnector
{
    protected function flow(): string
    {
        return 'INFORMRISK';
    }

    protected function key(): string
    {
        return '.IR_IX+INH_IX+V_IX+LCC_IX.A';
    }

    /** @return list<string> */
    protected function codeColumns(): array
    {
        return ['INDICATOR'];
    }

    protected function datasetKey(): string
    {
        return 'imf.inform_risk';
    }

    protected function datasetNameAr(): string
    {
        return 'مؤشر INFORM لمخاطر الكوارث والأزمات';
    }

    protected function datasetNameEn(): string
    {
        return 'INFORM Risk Index';
    }

    protected function section(): string
    {
        return 'macro';
    }

    protected function intervalMinutes(): int
    {
        return 30 * 24 * 60;
    }

    protected function indicators(): array
    {
        $rules = [new Range(0, 10, 'reject')];

        return [
            'IR_IX' => new IndicatorDefinition('imf.inform_risk', 'مؤشر INFORM للمخاطر (0–10، الأعلى أخطر)', 'INFORM Risk Index (0-10, higher = riskier)', 'درجة 0–10', 'A', $rules),
            'INH_IX' => new IndicatorDefinition('imf.inform_hazard', 'INFORM: التعرض للمخاطر (0–10)', 'INFORM: hazard and exposure', 'درجة 0–10', 'A', $rules),
            'V_IX' => new IndicatorDefinition('imf.inform_vulnerability', 'INFORM: الهشاشة (0–10)', 'INFORM: vulnerability', 'درجة 0–10', 'A', $rules),
            'LCC_IX' => new IndicatorDefinition('imf.inform_coping', 'INFORM: نقص القدرة على المواجهة (0–10)', 'INFORM: lack of coping capacity', 'درجة 0–10', 'A', $rules),
        ];
    }
}
