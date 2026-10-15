<?php

namespace App\Modules\WorldBank\Connectors;

final class WorldBankFinanceConnector extends WorldBankConnector
{
    protected function datasetKey(): string
    {
        return 'worldbank.finance';
    }

    protected function datasetNameAr(): string
    {
        return 'البنك الدولي: القطاع المالي والادخار والاستثمار';
    }

    protected function datasetNameEn(): string
    {
        return 'World Bank: finance, saving and investment';
    }

    protected function section(): string
    {
        return 'finance';
    }

    protected function indicators(): array
    {
        return [
            WbIndicator::ratio('FM.LBL.BMNY.GD.ZS', 'النقد الواسع', 'Broad money', '% من الناتج المحلي'),
            WbIndicator::ratio('FR.INR.LEND', 'سعر الإقراض', 'Lending interest rate', '%'),
            WbIndicator::ratio('FR.INR.RINR', 'سعر الفائدة الحقيقي', 'Real interest rate', '%'),
            WbIndicator::ratio('CM.MKT.LCAP.GD.ZS', 'القيمة السوقية للشركات المدرجة', 'Market capitalization of listed companies', '% من الناتج المحلي'),
            WbIndicator::share('FX.OWN.TOTL.ZS', 'امتلاك حساب مصرفي أو محفظة رقمية', 'Account ownership', '% من البالغين'),
            WbIndicator::ratio('NY.GNS.ICTR.ZS', 'الادخار الإجمالي', 'Gross savings', '% من الناتج المحلي'),
            WbIndicator::ratio('NE.GDI.TOTL.ZS', 'تكوين رأس المال الإجمالي', 'Gross capital formation', '% من الناتج المحلي'),
            WbIndicator::ratio('NE.CON.GOVT.ZS', 'الاستهلاك الحكومي', 'Government final consumption', '% من الناتج المحلي'),
            WbIndicator::level('BX.TRF.PWKR.CD.DT', 'تحويلات العاملين الواردة', 'Personal remittances received', 'دولار جارٍ'),
            WbIndicator::value('IS.SHP.GCNW.XQ', 'مؤشر الاتصال الملاحي', 'Liner shipping connectivity', 'مؤشر', 500),
            WbIndicator::value('LP.LPI.OVRL.XQ', 'مؤشر الأداء اللوجستي', 'Logistics performance index', '1–5', 5),
        ];
    }
}
