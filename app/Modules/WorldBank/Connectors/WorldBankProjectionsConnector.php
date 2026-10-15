<?php

namespace App\Modules\WorldBank\Connectors;

/**
 * التقديرات والتوقعات السكانية للبنك الدولي (مصدر 40) حتى 2050: الهرم العمري ونسب الإعالة والخصوبة والعمر المتوقع وصافي الهجرة.
 * مؤشراتها بادئة wbp. لأن مؤشرات السكان نفسها في WDI تحمل البادئة wb. وقيمها تاريخية فقط. السنوات بعد السنة الجارية توقعات.
 */
final class WorldBankProjectionsConnector extends WorldBankConnector
{
    protected function datasetKey(): string
    {
        return 'worldbank.projections';
    }

    protected function datasetNameAr(): string
    {
        return 'البنك الدولي: التوقعات السكانية حتى 2050';
    }

    protected function datasetNameEn(): string
    {
        return 'World Bank: population estimates and projections to 2050';
    }

    protected function section(): string
    {
        return 'society';
    }

    protected function keyPrefix(): string
    {
        return 'wbp.';
    }

    protected function sourceId(): int
    {
        return 40;
    }

    protected function sourceTitle(): string
    {
        return 'Population estimates and projections';
    }

    public function revision(): int
    {
        return 3;
    }

    protected function lastYear(): int
    {
        return 2050;
    }

    protected function intervalMinutes(): int
    {
        return 14 * 24 * 60;
    }

    protected function indicators(): array
    {
        return [
            WbIndicator::level('SP.POP.TOTL', 'عدد السكان (تقديرات وتوقعات)', 'Population, total (estimates and projections)', 'نسمة'),
            WbIndicator::level('SP.POP.1564.TO', 'السكان في سن العمل (15–64)', 'Population ages 15-64', 'نسمة'),
            WbIndicator::share('SP.POP.0014.TO.ZS', 'الأطفال دون 15 سنة', 'Population ages 0-14', '% من السكان'),
            WbIndicator::share('SP.POP.1564.TO.ZS', 'السكان 15–64 سنة', 'Population ages 15-64', '% من السكان'),
            WbIndicator::share('SP.POP.65UP.TO.ZS', 'السكان 65 سنة فأكثر', 'Population ages 65 and above', '% من السكان'),
            WbIndicator::ratio('SP.POP.DPND', 'نسبة الإعالة العمرية', 'Age dependency ratio', '% من السكان في سن العمل'),
            WbIndicator::ratio('SP.POP.DPND.YG', 'نسبة إعالة الصغار', 'Age dependency ratio, young', '% من السكان في سن العمل'),
            WbIndicator::ratio('SP.POP.DPND.OL', 'نسبة إعالة كبار السن', 'Age dependency ratio, old', '% من السكان في سن العمل'),
            WbIndicator::value('SP.DYN.LE00.IN', 'العمر المتوقع عند الولادة (توقعات)', 'Life expectancy at birth (projections)', 'سنة', 110),
            WbIndicator::value('SP.DYN.TFRT.IN', 'معدل الخصوبة (توقعات)', 'Fertility rate (projections)', 'مولود لكل امرأة', 15),
        ];
    }
}
