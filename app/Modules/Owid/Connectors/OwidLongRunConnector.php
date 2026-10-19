<?php

namespace App\Modules\Owid\Connectors;

/** مؤشرات طويلة المدى عبر OWID: الناتج للفرد منذ 1820 (Maddison)، السعرات والغذاء، الطاقة للفرد، النفط، النظام السياسي (Regimes of the World)، الجريمة، الفقر المدقع، الغابات والميثان والنفايات البلاستيكية. */
final class OwidLongRunConnector extends OwidCsvConnector
{
    protected function series(): array
    {
        return [
            'gdp-per-capita-maddison-project-database' => ['owid.gdp_pc_maddison', 'الناتج المحلي للفرد منذ 1820 (دولار 2011، Maddison)', 'GDP per capita since 1820 (Maddison)', 'دولار', 1e6],
            'daily-per-capita-caloric-supply' => ['owid.calories', 'السعرات اليومية المتاحة للفرد', 'Daily caloric supply per person', 'سعرة', 8000],
            'meat-supply-per-person' => ['owid.meat_supply', 'المتاح من اللحوم للفرد (كغ/سنة)', 'Meat supply per person', 'كغ', 500],
            'cereal-yield' => ['owid.cereal_yield', 'غلة الحبوب (طن/هكتار)', 'Cereal yield', 'طن/هكتار', 30],
            'per-capita-energy-use' => ['owid.energy_per_capita', 'استهلاك الطاقة الأولية للفرد (كيلوواط ساعة)', 'Primary energy use per person', 'كيلوواط ساعة', 1e6],
            'primary-energy-cons' => ['owid.energy_total', 'استهلاك الطاقة الأولية (تيراواط ساعة)', 'Primary energy consumption', 'تيراواط ساعة', 1e6],
            'renewable-share-energy' => ['owid.renewables_energy_share', 'حصة المتجددة من الطاقة الأولية (%)', 'Renewables share of primary energy', '%', 100],
            'oil-production-by-country' => ['owid.oil_production', 'إنتاج النفط (تيراواط ساعة)', 'Oil production', 'تيراواط ساعة', 1e6],
            'political-regime' => ['owid.political_regime', 'النظام السياسي (0 استبداد مغلق … 3 ديمقراطية كاملة)', 'Political regime (0 closed autocracy – 3 liberal democracy)', 'تصنيف', 3],
            'homicide-rate-unodc' => ['owid.homicide_rate', 'معدل جرائم القتل لكل 100 ألف (UNODC)', 'Homicide rate per 100,000 (UNODC)', 'لكل 100 ألف', 500],
            'share-of-population-in-extreme-poverty' => ['owid.extreme_poverty', 'الفقر المدقع (% تحت 3 دولار يوميًا)', 'Extreme poverty ($3/day)', '%', 100],
            'forest-area-km' => ['owid.forest_area', 'مساحة الغابات (كم²)', 'Forest area (km2)', 'كم²', 1e8],
            'methane-emissions' => ['owid.methane', 'انبعاثات الميثان (مكافئ CO2)', 'Methane emissions (CO2e)', 'طن', 2e10],
            'plastic-waste-per-capita' => ['owid.plastic_waste', 'النفايات البلاستيكية للفرد (كغ/يوم)', 'Plastic waste per capita', 'كغ/يوم', 5],
        ];
    }

    protected function datasetKey(): string
    {
        return 'owid.longrun';
    }

    protected function datasetNameAr(): string
    {
        return 'مؤشرات طويلة المدى: الغذاء والطاقة والنظام السياسي';
    }

    protected function datasetNameEn(): string
    {
        return 'Long-run indicators: food, energy, regime, poverty';
    }

    protected function section(): string
    {
        return 'society';
    }
}
