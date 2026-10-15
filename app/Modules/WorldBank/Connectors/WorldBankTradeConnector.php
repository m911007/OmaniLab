<?php

namespace App\Modules\WorldBank\Connectors;

final class WorldBankTradeConnector extends WorldBankConnector
{
    protected function datasetKey(): string
    {
        return 'worldbank.trade';
    }

    protected function datasetNameAr(): string
    {
        return 'البنك الدولي: التجارة والاستثمار الأجنبي وميزان المدفوعات';
    }

    protected function datasetNameEn(): string
    {
        return 'World Bank: trade, FDI and balance of payments';
    }

    protected function section(): string
    {
        return 'trade';
    }

    protected function indicators(): array
    {
        $gs = '% من الصادرات';
        $gm = '% من الواردات';

        return [
            WbIndicator::ratio('NE.EXP.GNFS.ZS', 'صادرات السلع والخدمات', 'Exports of goods and services', '% من الناتج المحلي'),
            WbIndicator::ratio('NE.IMP.GNFS.ZS', 'واردات السلع والخدمات', 'Imports of goods and services', '% من الناتج المحلي'),
            WbIndicator::ratio('NE.TRD.GNFS.ZS', 'انفتاح التجارة', 'Trade (% of GDP)', '% من الناتج المحلي'),
            WbIndicator::ratio('BN.CAB.XOKA.GD.ZS', 'رصيد الحساب الجاري', 'Current account balance', '% من الناتج المحلي'),
            WbIndicator::ratio('BX.KLT.DINV.WD.GD.ZS', 'صافي تدفقات الاستثمار الأجنبي المباشر الداخلة', 'FDI, net inflows', '% من الناتج المحلي'),
            WbIndicator::share('TX.VAL.TECH.MF.ZS', 'صادرات التقنية العالية', 'High-technology exports', '% من صادرات المصنّعات'),
            WbIndicator::share('TX.VAL.FUEL.ZS.UN', 'صادرات الوقود', 'Fuel exports', $gs),
            WbIndicator::share('TX.VAL.AGRI.ZS.UN', 'صادرات المواد الزراعية الخام', 'Agricultural raw materials exports', $gs),
            WbIndicator::share('TX.VAL.FOOD.ZS.UN', 'صادرات الأغذية', 'Food exports', $gs),
            WbIndicator::share('TX.VAL.MANF.ZS.UN', 'الصادرات المصنَّعة', 'Manufactures exports', $gs),
            WbIndicator::share('TX.VAL.MMTL.ZS.UN', 'صادرات الخامات والمعادن', 'Ores and metals exports', $gs),
            WbIndicator::share('TM.VAL.FUEL.ZS.UN', 'واردات الوقود', 'Fuel imports', $gm),
            WbIndicator::share('TM.VAL.AGRI.ZS.UN', 'واردات المواد الزراعية الخام', 'Agricultural raw materials imports', $gm),
            WbIndicator::share('TM.VAL.FOOD.ZS.UN', 'واردات الأغذية', 'Food imports', $gm),
            WbIndicator::share('TM.VAL.MANF.ZS.UN', 'الواردات المصنَّعة', 'Manufactures imports', $gm),
            WbIndicator::share('TM.VAL.MMTL.ZS.UN', 'واردات الخامات والمعادن', 'Ores and metals imports', $gm),
            WbIndicator::share('BX.GSR.TRVL.ZS', 'صادرات خدمات السفر والسياحة', 'Travel services exports', '% من صادرات الخدمات'),
            WbIndicator::share('BX.GSR.TRAN.ZS', 'صادرات خدمات النقل', 'Transport services exports', '% من صادرات الخدمات'),
            WbIndicator::share('BX.GSR.CCIS.ZS', 'صادرات خدمات الاتصالات والحاسوب والمعلومات', 'ICT services exports', '% من صادرات الخدمات'),
            WbIndicator::share('BX.GSR.INSF.ZS', 'صادرات الخدمات التأمينية والمالية', 'Insurance and financial services exports', '% من صادرات الخدمات'),
            WbIndicator::share('BM.GSR.TRVL.ZS', 'واردات خدمات السفر والسياحة', 'Travel services imports', '% من واردات الخدمات'),
            WbIndicator::share('BM.GSR.TRAN.ZS', 'واردات خدمات النقل', 'Transport services imports', '% من واردات الخدمات'),
            WbIndicator::share('BM.GSR.INSF.ZS', 'واردات الخدمات التأمينية والمالية', 'Insurance and financial services imports', '% من واردات الخدمات'),
        ];
    }
}
