<?php

namespace App\Modules\Owid\Connectors;

/**
 * مؤشرات عالمية مركّبة تنشرها جهات متخصصة وتجمعها OWID: مدركات الفساد (منظمة الشفافية الدولية)، السعادة (World Happiness Report)،
 * انبعاثات الكربون (Global Carbon Project). تُعرض مع أصلها في اسم المجموعة.
 */
final class OwidIndicesConnector extends OwidCsvConnector
{
    protected function series(): array
    {
        return [
            'ti-corruption-perception-index' => ['owid.cpi_score', 'مؤشر مدركات الفساد (الأعلى = أنظف)', 'Corruption Perceptions Index (Transparency International)', 'درجة 0–100', 100],
            'happiness-cantril-ladder' => ['owid.happiness', 'مؤشر السعادة (سلّم كانتريل)', 'Happiness: Cantril ladder (World Happiness Report)', 'درجة 0–10', 10],
            'co2-emissions-per-capita' => ['owid.co2_per_capita', 'انبعاثات CO₂ للفرد (Global Carbon Project)', 'CO2 emissions per capita (Global Carbon Project)', 'طن', 1000],
            'annual-co2-emissions-per-country' => ['owid.co2_total', 'إجمالي انبعاثات CO₂', 'Annual CO2 emissions (Global Carbon Project)', 'طن', 2e11],
        ];
    }

    protected function datasetKey(): string
    {
        return 'owid.indices';
    }

    protected function datasetNameAr(): string
    {
        return 'مؤشرات عالمية: الفساد والسعادة والكربون';
    }

    protected function datasetNameEn(): string
    {
        return 'Global indices: corruption, happiness, carbon';
    }

    protected function section(): string
    {
        return 'governance';
    }
}
