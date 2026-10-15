<?php

namespace App\Modules\WorldBank\Connectors;

final class WorldBankSocietyConnector extends WorldBankConnector
{
    protected function datasetKey(): string
    {
        return 'worldbank.society';
    }

    protected function datasetNameAr(): string
    {
        return 'البنك الدولي: السكان والعمل والتعليم';
    }

    protected function datasetNameEn(): string
    {
        return 'World Bank: population, labour and education';
    }

    protected function section(): string
    {
        return 'society';
    }

    protected function indicators(): array
    {
        return [
            WbIndicator::level('SP.POP.TOTL', 'إجمالي السكان', 'Population, total', 'نسمة'),
            WbIndicator::ratio('SP.POP.GROW', 'معدل نمو السكان', 'Population growth', '%'),
            WbIndicator::value('SP.DYN.LE00.IN', 'متوسط العمر المتوقع عند الولادة', 'Life expectancy at birth', 'سنة', 130),
            WbIndicator::share('SP.URB.TOTL.IN.ZS', 'نسبة سكان الحضر', 'Urban population', '% من السكان'),
            WbIndicator::share('SP.POP.65UP.TO.ZS', 'نسبة السكان بعمر 65 فأكثر', 'Population ages 65+', '% من السكان'),
            WbIndicator::share('SL.UEM.TOTL.ZS', 'معدل البطالة', 'Unemployment, total', '% من القوى العاملة'),
            WbIndicator::share('SL.UEM.1524.ZS', 'بطالة الشباب (15–24)', 'Youth unemployment', '% من القوى العاملة'),
            WbIndicator::share('SL.TLF.CACT.ZS', 'معدل المشاركة في القوى العاملة (15+)', 'Labour force participation rate', '%'),
            WbIndicator::share('SL.TLF.ADVN.ZS', 'القوى العاملة ذات التعليم العالي', 'Labour force with advanced education', '%'),
            WbIndicator::ratio('SE.TER.ENRR', 'الالتحاق بالتعليم العالي', 'Tertiary school enrollment', '%'),
            WbIndicator::ratio('SE.SEC.ENRR', 'الالتحاق بالتعليم الثانوي', 'Secondary school enrollment', '%'),
            WbIndicator::ratio('SE.PRM.ENRR', 'الالتحاق بالتعليم الابتدائي', 'Primary school enrollment', '%'),
            WbIndicator::ratio('SE.PRM.CMPT.ZS', 'إتمام التعليم الابتدائي', 'Primary completion rate', '%'),
            WbIndicator::share('SE.ADT.LITR.ZS', 'إلمام الكبار بالقراءة والكتابة', 'Adult literacy rate', '%'),
            WbIndicator::share('SE.ADT.1524.LT.ZS', 'إلمام الشباب بالقراءة والكتابة', 'Youth literacy rate', '%'),
            WbIndicator::ratio('SE.XPD.TOTL.GD.ZS', 'الإنفاق على التعليم', 'Government expenditure on education', '% من الناتج المحلي'),
            WbIndicator::share('SE.XPD.TOTL.GB.ZS', 'حصة التعليم من الإنفاق الحكومي', 'Education share of government expenditure', '%'),
            WbIndicator::share('SE.TER.CUAT.BA.ZS', 'السكان الحاصلون على بكالوريوس (25+)', 'Bachelor\'s attainment, 25+', '%'),
            WbIndicator::value('HD.HCI.OVRL', 'مؤشر رأس المال البشري', 'Human Capital Index', '0–1', 1.0),
            WbIndicator::value('SE.SCH.LIFE', 'العمر المتوقع للتعليم المدرسي', 'Expected years of schooling', 'سنة', 40),
        ];
    }
}
