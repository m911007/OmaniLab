<?php

namespace App\Modules\WorldBank\Connectors;

final class WorldBankHealthConnector extends WorldBankConnector
{
    protected function datasetKey(): string
    {
        return 'worldbank.health';
    }

    protected function datasetNameAr(): string
    {
        return 'البنك الدولي: الصحة والتركيبة السكانية';
    }

    protected function datasetNameEn(): string
    {
        return 'World Bank: health and demographics';
    }

    protected function section(): string
    {
        return 'society';
    }

    protected function indicators(): array
    {
        return [
            WbIndicator::ratio('SH.XPD.CHEX.GD.ZS', 'الإنفاق الصحي الجاري', 'Current health expenditure', '% من الناتج المحلي'),
            WbIndicator::value('SP.DYN.IMRT.IN', 'وفيات الرضع', 'Infant mortality rate', 'لكل 1000 مولود'),
            WbIndicator::value('SH.DYN.MORT', 'وفيات الأطفال دون الخامسة', 'Under-5 mortality rate', 'لكل 1000 مولود'),
            WbIndicator::value('SH.STA.MMRT', 'وفيات الأمهات', 'Maternal mortality ratio', 'لكل 100 ألف مولود'),
            WbIndicator::value('SH.MED.PHYS.ZS', 'الأطباء', 'Physicians', 'لكل 1000 نسمة', 30),
            WbIndicator::value('SH.MED.BEDS.ZS', 'أسرّة المستشفيات', 'Hospital beds', 'لكل 1000 نسمة', 60),
            WbIndicator::value('SP.DYN.TFRT.IN', 'معدل الخصوبة', 'Fertility rate', 'مولود لكل امرأة', 12),
            WbIndicator::share('SP.POP.0014.TO.ZS', 'السكان دون 15 سنة', 'Population ages 0-14', '% من السكان'),
            WbIndicator::share('SP.POP.1564.TO.ZS', 'السكان بين 15 و64 سنة', 'Population ages 15-64', '% من السكان'),
            WbIndicator::ratio('SM.POP.NETM', 'صافي الهجرة', 'Net migration', 'نسمة'),
            WbIndicator::share('SM.POP.TOTL.ZS', 'المهاجرون الدوليون', 'International migrant stock', '% من السكان'),
            WbIndicator::share('SP.RUR.TOTL.ZS', 'سكان الريف', 'Rural population', '% من السكان'),
        ];
    }
}
