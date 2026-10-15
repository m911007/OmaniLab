<?php

namespace App\Modules\WorldBank\Connectors;

final class WorldBankBankingConnector extends WorldBankConnector
{
    protected function datasetKey(): string
    {
        return 'worldbank.banking';
    }

    protected function datasetNameAr(): string
    {
        return 'البنك الدولي: سلامة القطاع المصرفي';
    }

    protected function datasetNameEn(): string
    {
        return 'World Bank: banking sector soundness';
    }

    protected function section(): string
    {
        return 'finance';
    }

    protected function indicators(): array
    {
        return [
            WbIndicator::value('GFDD.SI.01', 'مؤشر Z للبنوك (بُعد الإعسار)', 'Bank Z-score', 'مؤشر', 500),
            WbIndicator::share('GFDD.SI.02', 'القروض المتعثرة إلى إجمالي القروض', 'Bank non-performing loans to gross loans', '%'),
            WbIndicator::share('GFDD.SI.03', 'رأس مال البنوك إلى الأصول', 'Bank capital to total assets', '%'),
            WbIndicator::share('GFDD.SI.05', 'رأس المال التنظيمي إلى الأصول المرجّحة بالمخاطر', 'Bank regulatory capital to risk-weighted assets', '%'),
            WbIndicator::ratio('GFDD.SI.04', 'قروض البنوك إلى ودائعها', 'Bank credit to bank deposits', '%'),
            WbIndicator::share('GFDD.OI.06', 'تركّز أصول أكبر 5 بنوك', '5-bank asset concentration', '%'),
            WbIndicator::ratio('GFDD.EI.01', 'هامش صافي الفائدة للبنوك', 'Bank net interest margin', '%'),
            WbIndicator::ratio('GFDD.EI.02', 'فارق الإقراض والإيداع', 'Bank lending-deposit spread', '%'),
            WbIndicator::ratio('GFDD.DI.14', 'الائتمان المحلي للقطاع الخاص (قاعدة التطور المالي)', 'Domestic credit to private sector (GFDD)', '% من الناتج المحلي'),
        ];
    }
}
