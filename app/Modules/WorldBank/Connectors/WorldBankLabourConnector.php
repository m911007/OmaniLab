<?php

namespace App\Modules\WorldBank\Connectors;

final class WorldBankLabourConnector extends WorldBankConnector
{
    protected function datasetKey(): string
    {
        return 'worldbank.labour';
    }

    protected function datasetNameAr(): string
    {
        return 'البنك الدولي: العمل والتوظيف والمساواة';
    }

    protected function datasetNameEn(): string
    {
        return 'World Bank: labour, employment and equality';
    }

    protected function section(): string
    {
        return 'society';
    }

    protected function indicators(): array
    {
        return [
            WbIndicator::share('SL.EMP.TOTL.SP.ZS', 'نسبة العاملين إلى السكان (15+)', 'Employment to population ratio', '%'),
            WbIndicator::share('SL.SRV.EMPL.ZS', 'التوظيف في الخدمات', 'Employment in services', '% من العاملين'),
            WbIndicator::share('SL.IND.EMPL.ZS', 'التوظيف في الصناعة', 'Employment in industry', '% من العاملين'),
            WbIndicator::share('SL.AGR.EMPL.ZS', 'التوظيف في الزراعة', 'Employment in agriculture', '% من العاملين'),
            WbIndicator::share('SG.GEN.PARL.ZS', 'المقاعد النيابية للنساء', 'Seats held by women in parliament', '%'),
            WbIndicator::share('SI.POV.GINI', 'معامل جيني للتفاوت', 'Gini index', '0–100'),
        ];
    }
}
