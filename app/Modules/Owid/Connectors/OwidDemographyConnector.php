<?php

namespace App\Modules\Owid\Connectors;

/** التركيبة السكانية (تقديرات وإسقاطات الأمم المتحدة WPP عبر OWID): عدد السكان حتى 2100 (السيناريو المتوسط)، العمر الوسيط، العمر المتوقع، نسبة سكان الحضر. */
final class OwidDemographyConnector extends OwidCsvConnector
{
    protected function series(): array
    {
        return [
            'population-with-un-projections#3' => ['owid.population', 'عدد السكان (تقديرات الأمم المتحدة)', 'Population (UN estimates)', 'نسمة', 2e10],
            'population-with-un-projections#4' => ['owid.population_proj', 'عدد السكان (إسقاط الأمم المتحدة المتوسط حتى 2100)', 'Population (UN medium projection to 2100)', 'نسمة', 2e10],
            'median-age#3' => ['owid.median_age', 'العمر الوسيط (تقديرات)', 'Median age (UN estimates)', 'سنة', 80],
            'median-age#4' => ['owid.median_age_proj', 'العمر الوسيط (إسقاط الأمم المتحدة حتى 2100)', 'Median age (UN projection)', 'سنة', 80],
            'life-expectancy#3' => ['owid.life_expectancy_long', 'العمر المتوقع عند الميلاد (سلسلة طويلة منذ 1543)', 'Life expectancy (long run)', 'سنة', 100],
            'share-of-population-urban#3' => ['owid.urban_share', 'نسبة سكان الحضر (تقديرات الأمم المتحدة)', 'Urban population share (UN estimates)', '%', 100],
        ];
    }

    protected function datasetKey(): string
    {
        return 'owid.demography';
    }

    protected function datasetNameAr(): string
    {
        return 'السكان: التوقعات الأممية حتى 2100 والعمر والتحضر';
    }

    protected function datasetNameEn(): string
    {
        return 'Population projections to 2100, age, urbanisation';
    }

    protected function section(): string
    {
        return 'society';
    }
}
