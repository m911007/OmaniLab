<?php

namespace App\Modules\WorldBank\Connectors;

/**
 * مؤشرات إضافية للبنك الدولي تكمل المجموعات المتخصصة: العمل (الأجر والعمل الحر)، والحضر، والمحميات، والسلامة المرورية،
 * والإنفاق الصحي من الجيب، والقدرة الإحصائية، والادخار والاستثمار الثابت، والنقل الجوي، وفقد الكهرباء، والانتشار المصرفي، وكثافة الأعمال الجديدة.
 */
final class WorldBankExtraConnector extends WorldBankConnector
{
    protected function datasetKey(): string
    {
        return 'worldbank.extra';
    }

    protected function datasetNameAr(): string
    {
        return 'البنك الدولي: مؤشرات إضافية (عمل، حضر، نقل، مصارف)';
    }

    protected function datasetNameEn(): string
    {
        return 'World Bank: additional indicators (labour, urban, transport, banking access)';
    }

    protected function section(): string
    {
        return 'society';
    }

    protected function indicators(): array
    {
        return [
            WbIndicator::share('SL.TLF.CACT.FE.ZS', 'مشاركة المرأة في القوى العاملة (تقدير مُنمذج)', 'Female labour force participation (modelled ILO)', '% من النساء 15+'),
            WbIndicator::share('SL.EMP.WORK.ZS', 'العاملون بأجر', 'Wage and salaried workers', '% من العاملين'),
            WbIndicator::share('SL.EMP.SELF.ZS', 'العاملون لحسابهم', 'Self-employed', '% من العاملين'),
            WbIndicator::share('SL.EMP.VULN.FE.ZS', 'التوظيف الهش للنساء', 'Vulnerable employment, female', '% من توظيف النساء'),
            WbIndicator::ratio('SP.URB.GROW', 'نمو سكان الحضر', 'Urban population growth', '%'),
            WbIndicator::share('EN.URB.LCTY.UR.ZS', 'سكان أكبر مدينة', 'Population in the largest city', '% من الحضر'),
            WbIndicator::share('ER.PTD.TOTL.ZS', 'المناطق المحمية البرية والبحرية', 'Protected areas', '% من المساحة'),
            WbIndicator::value('SH.STA.TRAF.P5', 'الوفيات المرورية', 'Road traffic mortality', 'لكل 100 ألف', 500),
            WbIndicator::share('SH.XPD.OOPC.CH.ZS', 'الإنفاق الصحي من الجيب', 'Out-of-pocket health expenditure', '% من الإنفاق الصحي'),
            WbIndicator::value('IQ.SPI.OVRL', 'مؤشر الأداء الإحصائي', 'Statistical performance indicator', '0–100', 100),
            WbIndicator::ratio('NE.GDI.FTOT.ZS', 'تكوين رأس المال الثابت', 'Gross fixed capital formation', '% من الناتج'),
            WbIndicator::ratio('NY.GDS.TOTL.ZS', 'الادخار المحلي الإجمالي', 'Gross domestic savings', '% من الناتج'),
            WbIndicator::level('TX.VAL.SERV.CD.WT', 'صادرات الخدمات التجارية', 'Commercial service exports', 'دولار جارٍ'),
            WbIndicator::ratio('BX.KLT.DINV.CD.WD', 'صافي تدفقات الاستثمار الأجنبي المباشر (بالدولار)', 'FDI net inflows (US$)', 'دولار جارٍ'),
            WbIndicator::level('IS.AIR.PSGR', 'ركاب النقل الجوي', 'Air transport, passengers carried', 'راكب'),
            WbIndicator::level('IS.AIR.GOOD.MT.K1', 'شحن النقل الجوي', 'Air transport, freight', 'مليون طن-كم'),
            WbIndicator::share('EG.ELC.LOSS.ZS', 'فقد نقل وتوزيع الكهرباء', 'Electric power T&D losses', '% من الإنتاج'),
            WbIndicator::ratio('EG.IMP.CONS.ZS', 'صافي واردات الطاقة (سالب = مصدّر)', 'Net energy imports (negative = exporter)', '% من استخدام الطاقة'),
            WbIndicator::value('FB.ATM.TOTL.P5', 'أجهزة الصراف الآلي', 'ATMs', 'لكل 100 ألف بالغ', 5000),
            WbIndicator::value('FB.CBK.BRCH.P5', 'فروع البنوك التجارية', 'Commercial bank branches', 'لكل 100 ألف بالغ', 500),
            WbIndicator::value('IC.BUS.NDNS.ZS', 'كثافة الأعمال الجديدة', 'New business density', 'لكل 1000 نسمة', 500),
        ];
    }
}
