<?php

namespace App\Modules\WorldBank\Connectors;

/**
 * الموارد الطبيعية (البنك الدولي): ريوع الغاز والمعادن والفحم والغابات والإجمالي، والأرض الزراعية والري، واستخدامات المياه العذبة،
 * ومصايد الأسماك، وإنتاج الحبوب. تكمل ريع النفط وإجهاد المياه الموجودين في مجموعات أخرى.
 */
final class WorldBankResourcesConnector extends WorldBankConnector
{
    protected function datasetKey(): string
    {
        return 'worldbank.resources';
    }

    protected function datasetNameAr(): string
    {
        return 'البنك الدولي: الموارد الطبيعية والمياه والزراعة والأسماك';
    }

    protected function datasetNameEn(): string
    {
        return 'World Bank: natural resources, water, agriculture, fisheries';
    }

    protected function section(): string
    {
        return 'energy';
    }

    protected function indicators(): array
    {
        return [
            WbIndicator::ratio('NY.GDP.TOTL.RT.ZS', 'إجمالي ريوع الموارد الطبيعية', 'Total natural resources rents', '% من الناتج'),
            WbIndicator::ratio('NY.GDP.NGAS.RT.ZS', 'ريع الغاز الطبيعي', 'Natural gas rents', '% من الناتج'),
            WbIndicator::ratio('NY.GDP.MINR.RT.ZS', 'ريع المعادن', 'Mineral rents', '% من الناتج'),
            WbIndicator::ratio('NY.GDP.COAL.RT.ZS', 'ريع الفحم', 'Coal rents', '% من الناتج'),
            WbIndicator::ratio('NY.GDP.FRST.RT.ZS', 'ريع الغابات', 'Forest rents', '% من الناتج'),
            WbIndicator::share('AG.LND.AGRI.ZS', 'الأراضي الزراعية', 'Agricultural land', '% من مساحة الأرض'),
            WbIndicator::share('AG.LND.ARBL.ZS', 'الأراضي الصالحة للحراثة', 'Arable land', '% من مساحة الأرض'),
            WbIndicator::share('AG.LND.IRIG.AG.ZS', 'الأراضي الزراعية المروية', 'Irrigated agricultural land', '% من الأرض الزراعية'),
            WbIndicator::share('ER.H2O.FWAG.ZS', 'سحب المياه العذبة للزراعة', 'Freshwater withdrawals: agriculture', '% من إجمالي السحب'),
            WbIndicator::share('ER.H2O.FWDM.ZS', 'سحب المياه العذبة للاستخدام المنزلي', 'Freshwater withdrawals: domestic', '% من إجمالي السحب'),
            WbIndicator::share('ER.H2O.FWIN.ZS', 'سحب المياه العذبة للصناعة', 'Freshwater withdrawals: industry', '% من إجمالي السحب'),
            // المياه (AQUASTAT عبر البنك الدولي): القيمة الاقتصادية للمتر المكعب، وحجم السحب مقابل الموارد المتجددة
            WbIndicator::value('ER.GDP.FWTL.M3.KD', 'إنتاجية المياه: الناتج لكل متر مكعب مسحوب', 'Water productivity, total (constant 2015 US$ GDP per m3 of total freshwater withdrawal)', 'دولار (أسعار 2015) لكل م³', 1e6),
            WbIndicator::value('ER.H2O.FWTL.K3', 'إجمالي سحب المياه العذبة', 'Annual freshwater withdrawals, total', 'مليار م³', 1e5),
            WbIndicator::value('ER.H2O.INTR.K3', 'الموارد المائية الداخلية المتجددة', 'Renewable internal freshwater resources, total', 'مليار م³', 1e5),
            WbIndicator::value('ER.H2O.FWTL.ZS', 'سحب المياه العذبة إلى الموارد المتجددة', 'Annual freshwater withdrawals (% of internal resources)', '% من الموارد المتجددة', 1e6),
            WbIndicator::level('ER.FSH.CAPT.MT', 'صيد الأسماك البري', 'Capture fisheries production', 'طن'),
            WbIndicator::level('ER.FSH.PROD.MT', 'إجمالي إنتاج الأسماك', 'Total fisheries production', 'طن'),
            WbIndicator::share('NE.CON.PRVT.ZS', 'الاستهلاك الأسري', 'Household final consumption', '% من الناتج'),
            WbIndicator::share('ST.INT.RCPT.XP.ZS', 'إيرادات السياحة الدولية', 'International tourism receipts', '% من الصادرات'),
            WbIndicator::ratio('BX.TRF.PWKR.DT.GD.ZS', 'تحويلات العاملين الواردة', 'Personal remittances received', '% من الناتج'),
            WbIndicator::level('AG.PRD.CREL.MT', 'إنتاج الحبوب', 'Cereal production', 'طن'),
        ];
    }
}
