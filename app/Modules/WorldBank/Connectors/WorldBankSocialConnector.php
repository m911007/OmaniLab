<?php

namespace App\Modules\WorldBank\Connectors;

/**
 * مؤشرات اجتماعية ومالية إضافية للبنك الدولي لإثراء ملف كل دولة: تمثيل المرأة والقوانين، الفقر وعدم المساواة، وفيات الأمهات والتقزّم،
 * المياه والصرف الصحي، نفقات الحكومة المركزية وإيراداتها، أسعار الإقراض، القروض المتعثرة، التعرفة الجمركية، الأسمدة وغلة الحبوب،
 * وانبعاثات الغازات. كثير منها لا يتوفر لعُمان لكنه متوفر لدول المقارنة والعالم.
 */
final class WorldBankSocialConnector extends WorldBankConnector
{
    protected function datasetKey(): string
    {
        return 'worldbank.social';
    }

    protected function datasetNameAr(): string
    {
        return 'البنك الدولي: مؤشرات اجتماعية ومالية إضافية';
    }

    protected function datasetNameEn(): string
    {
        return 'World Bank: additional social and financial indicators';
    }

    protected function section(): string
    {
        return 'society';
    }

    protected function indicators(): array
    {
        return [
            WbIndicator::value('SG.LAW.INDX', 'مؤشر المرأة والأعمال والقانون', 'Women, Business and the Law index', '0–100', 100),
            WbIndicator::share('SI.POV.DDAY', 'الفقر المدقع (2.15 دولار يوميًا)', 'Extreme poverty ($2.15/day)', '% من السكان'),
            WbIndicator::share('SI.DST.10TH.10', 'حصة أغنى 10% من الدخل', 'Income share held by top 10%', '%'),
            WbIndicator::share('SI.DST.FRST.20', 'حصة أفقر 20% من الدخل', 'Income share held by bottom 20%', '%'),
            WbIndicator::share('SH.STA.STNT.ZS', 'تقزّم الأطفال دون الخامسة', 'Stunting, children under 5', '%'),
            WbIndicator::share('SH.H2O.BASW.ZS', 'خدمات مياه الشرب الأساسية', 'Basic drinking water services', '% من السكان'),
            WbIndicator::share('SH.STA.BASS.ZS', 'خدمات الصرف الصحي الأساسية', 'Basic sanitation services', '% من السكان'),
            WbIndicator::ratio('GC.XPN.TOTL.GD.ZS', 'مصروفات الحكومة المركزية', 'Central government expense', '% من الناتج'),
            WbIndicator::ratio('GC.REV.XGRT.GD.ZS', 'إيرادات الحكومة المركزية (دون المنح)', 'Central government revenue excl. grants', '% من الناتج'),
            WbIndicator::ratio('FR.INR.LNDP', 'هامش الإقراض (إقراض ناقص إيداع)', 'Interest rate spread', '%'),
            WbIndicator::share('FB.AST.NPER.ZS', 'القروض المتعثرة إلى إجمالي القروض', 'Bank non-performing loans', '%'),
            WbIndicator::ratio('TM.TAX.MRCH.WM.AR.ZS', 'متوسط التعرفة الجمركية', 'Mean applied tariff rate', '%'),
            WbIndicator::value('AG.YLD.CREL.KG', 'غلة الحبوب (كغم/هكتار)', 'Cereal yield', 'كغم/هكتار', 100000),
            WbIndicator::value('AG.CON.FERT.ZS', 'استهلاك الأسمدة (كغم/هكتار)', 'Fertilizer consumption', 'كغم/هكتار', 100000),
            WbIndicator::level('EN.GHG.CO2.MT.CE.AR5', 'انبعاثات CO₂ (ميغاطن مكافئ)', 'CO2 emissions (Mt CO2e)', 'ميغاطن'),
            WbIndicator::level('EN.GHG.CH4.MT.CE.AR5', 'انبعاثات الميثان (ميغاطن مكافئ)', 'Methane emissions (Mt CO2e)', 'ميغاطن'),
            WbIndicator::share('SE.PRM.TCHR', 'نسبة التلاميذ إلى المعلمين (ابتدائي)', 'Pupil-teacher ratio, primary', 'تلميذ/معلم'),
            WbIndicator::share('SL.TLF.PART.FE.ZS', 'مشاركة المرأة في القوى العاملة (وطني)', 'Female labour force participation (national)', '%'),
        ];
    }
}
