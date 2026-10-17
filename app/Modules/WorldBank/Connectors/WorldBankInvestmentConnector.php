<?php

namespace App\Modules\WorldBank\Connectors;

/**
 * الاستثمار وأسواق المال (البنك الدولي): الاستثمار الأجنبي المباشر الصادر وصافيه، وتدفقات المحافظ في الأسهم،
 * وحجم السوق المالية (القيمة السوقية، الشركات المدرجة، دوران الأسهم)، والاستثمار الخاص، وسعر الفائدة الحقيقي.
 */
final class WorldBankInvestmentConnector extends WorldBankConnector
{
    protected function datasetKey(): string
    {
        return 'worldbank.investment';
    }

    protected function datasetNameAr(): string
    {
        return 'البنك الدولي: الاستثمار وأسواق المال';
    }

    protected function datasetNameEn(): string
    {
        return 'World Bank: investment and capital markets';
    }

    protected function section(): string
    {
        return 'finance';
    }

    protected function indicators(): array
    {
        return [
            WbIndicator::ratio('BM.KLT.DINV.WD.GD.ZS', 'الاستثمار الأجنبي المباشر الصادر (صافي)', 'FDI net outflows', '% من الناتج'),
            WbIndicator::ratio('BN.KLT.DINV.CD', 'صافي الاستثمار الأجنبي المباشر (ميزان المدفوعات)', 'FDI net (BoP, current US$)', 'دولار جارٍ'),
            WbIndicator::ratio('BX.PEF.TOTL.CD.WD', 'تدفقات المحافظ في الأسهم (صافي)', 'Portfolio equity net inflows', 'دولار جارٍ'),
            WbIndicator::level('CM.MKT.LCAP.CD', 'القيمة السوقية للشركات المدرجة (بالدولار)', 'Market capitalisation (US$)', 'دولار جارٍ'),
            WbIndicator::value('CM.MKT.TRNR', 'معدل دوران الأسهم', 'Stocks traded, turnover ratio', '%', 5000),
            WbIndicator::ratio('NE.GDI.FPRV.ZS', 'تكوين رأس المال الثابت الخاص', 'Private gross fixed capital formation', '% من الناتج'),
        ];
    }
}
