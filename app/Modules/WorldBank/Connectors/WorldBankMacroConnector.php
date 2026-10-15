<?php

namespace App\Modules\WorldBank\Connectors;

final class WorldBankMacroConnector extends WorldBankConnector
{
    protected function datasetKey(): string
    {
        return 'worldbank.macro';
    }

    protected function datasetNameAr(): string
    {
        return 'البنك الدولي: الاقتصاد الكلي والمالية العامة';
    }

    protected function datasetNameEn(): string
    {
        return 'World Bank: macroeconomy and public finance';
    }

    protected function section(): string
    {
        return 'macro';
    }

    protected function indicators(): array
    {
        return [
            WbIndicator::level('NY.GDP.MKTP.CD', 'الناتج المحلي الإجمالي', 'GDP (current US$)', 'دولار جارٍ'),
            WbIndicator::level('NY.GDP.PCAP.CD', 'نصيب الفرد من الناتج المحلي', 'GDP per capita (current US$)', 'دولار جارٍ'),
            WbIndicator::level('NY.GNP.PCAP.CD', 'نصيب الفرد من الدخل القومي (أطلس)', 'GNI per capita, Atlas method', 'دولار جارٍ'),
            WbIndicator::ratio('FP.CPI.TOTL.ZG', 'التضخم — أسعار المستهلك', 'Inflation, consumer prices (annual %)', '%'),
            WbIndicator::ratio('GC.DOD.TOTL.GD.ZS', 'دين الحكومة المركزية', 'Central government debt, total', '% من الناتج المحلي'),
            WbIndicator::ratio('GC.TAX.TOTL.GD.ZS', 'إيرادات الضرائب', 'Tax revenue', '% من الناتج المحلي'),
            WbIndicator::level('FI.RES.TOTL.CD', 'إجمالي الاحتياطيات (تشمل الذهب)', 'Total reserves including gold', 'دولار جارٍ'),
            WbIndicator::value('PA.NUS.FCRF', 'سعر الصرف الرسمي', 'Official exchange rate (LCU per US$)', 'عملة محلية/دولار'),
            WbIndicator::ratio('FS.AST.PRVT.GD.ZS', 'الائتمان المحلي للقطاع الخاص', 'Domestic credit to private sector', '% من الناتج المحلي'),
            WbIndicator::ratio('NY.GDP.PETR.RT.ZS', 'ريع النفط', 'Oil rents', '% من الناتج المحلي'),
            WbIndicator::ratio('NV.IND.TOTL.ZS', 'القيمة المضافة للصناعة', 'Industry value added', '% من الناتج المحلي'),
            WbIndicator::ratio('NV.SRV.TOTL.ZS', 'القيمة المضافة للخدمات', 'Services value added', '% من الناتج المحلي'),
            WbIndicator::ratio('NV.AGR.TOTL.ZS', 'القيمة المضافة للزراعة', 'Agriculture value added', '% من الناتج المحلي'),
            WbIndicator::ratio('NV.IND.MANF.ZS', 'القيمة المضافة للصناعات التحويلية', 'Manufacturing value added', '% من الناتج المحلي'),
            WbIndicator::ratio('MS.MIL.XPND.GD.ZS', 'الإنفاق العسكري', 'Military expenditure', '% من الناتج المحلي'),
        ];
    }
}
