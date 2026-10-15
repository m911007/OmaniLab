<?php

namespace App\Modules\Macro\Connectors;

use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Validation\Rules\Completeness;
use App\Core\Validation\Rules\MaxRevision;
use App\Core\Validation\Rules\Range;

/** مرصد المالية العامة لصندوق النقد (Fiscal Monitor): الإيرادات والمصروفات والأرصدة وصافي الدين، كنسب من الناتج. */
final class ImfFiscalMonitorConnector extends ImfDataMapperConnector
{
    protected function datasetKey(): string
    {
        return 'imf.fiscal_monitor';
    }

    protected function datasetNameAr(): string
    {
        return 'مرصد المالية العامة (IMF Fiscal Monitor)';
    }

    protected function datasetNameEn(): string
    {
        return 'IMF Fiscal Monitor';
    }

    protected function section(): string
    {
        return 'fiscal';
    }

    /** @return list<IndicatorDefinition> */
    protected function indicators(): array
    {
        // قيم متطرفة حقيقية لدول صغيرة (دين صافٍ 776%، عجز 549%)؛ المستحيل الصريح فقط يُستبعد.
        $pct = fn (float $delta = 10.0) => [new Range(-5_000, 5_000, 'reject'), new MaxRevision($delta, 0.25), new Completeness(0.15)];
        $potential = [new Range(-200, 200, 'reject'), new MaxRevision(5.0, 0.25), new Completeness(0.15)];

        return [
            new IndicatorDefinition('imf.GGR_G01_GDP_PT', 'الإيرادات الحكومية', 'Government revenue', '% من الناتج', rules: $pct()),
            new IndicatorDefinition('imf.G_X_G01_GDP_PT', 'المصروفات الحكومية', 'Government expenditure', '% من الناتج', rules: $pct()),
            new IndicatorDefinition('imf.GGXCNL_G01_GDP_PT', 'الرصيد الكلي للميزانية', 'Overall fiscal balance', '% من الناتج', rules: $pct()),
            new IndicatorDefinition('imf.GGXONLB_G01_GDP_PT', 'الرصيد الأولي (قبل الفوائد)', 'Primary balance', '% من الناتج', rules: $pct()),
            new IndicatorDefinition('imf.GGCB_G01_PGDP_PT', 'الرصيد المعدَّل دوريًا', 'Cyclically adjusted balance', '% من الناتج الكامن', rules: $potential),
            new IndicatorDefinition('imf.GGCBP_G01_PGDP_PT', 'الرصيد الأولي المعدَّل دوريًا', 'Cyclically adjusted primary balance', '% من الناتج الكامن', rules: $potential),
            new IndicatorDefinition('imf.G_XWDG_G01_GDP_PT', 'إجمالي الدين الحكومي (مرصد المالية)', 'Gross debt position', '% من الناتج', rules: $pct(15.0)),
            new IndicatorDefinition('imf.GGXWDN_G01_GDP_PT', 'صافي الدين الحكومي', 'Net debt', '% من الناتج', rules: $pct(15.0)),
        ];
    }
}
