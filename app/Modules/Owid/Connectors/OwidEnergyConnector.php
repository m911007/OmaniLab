<?php

namespace App\Modules\Owid\Connectors;

/** مزيج الكهرباء وكثافتها الكربونية لكل الدول حتى العام الأخير (Ember و Energy Institute): أحدث من البنك الدولي بأربع سنوات. */
final class OwidEnergyConnector extends OwidCsvConnector
{
    protected function series(): array
    {
        return [
            'share-electricity-renewables' => ['owid.renewable_electricity_share', 'حصة المتجددة من الكهرباء', 'Renewable share of electricity', '%', 100],
            'share-electricity-solar' => ['owid.solar_electricity_share', 'حصة الشمس من الكهرباء', 'Solar share of electricity', '%', 100],
            'share-electricity-wind' => ['owid.wind_electricity_share', 'حصة الرياح من الكهرباء', 'Wind share of electricity', '%', 100],
            'carbon-intensity-electricity' => ['owid.electricity_carbon_intensity', 'كثافة الكربون في الكهرباء', 'Carbon intensity of electricity', 'غرام CO₂/كيلوواط ساعة', 3000],
            'per-capita-electricity-generation' => ['owid.electricity_generation_per_capita', 'توليد الكهرباء للفرد', 'Electricity generation per capita', 'كيلوواط ساعة', 200_000],
            'electricity-demand' => ['owid.electricity_demand', 'الطلب على الكهرباء', 'Electricity demand', 'تيراواط ساعة', 10_000],
            'per-capita-energy-use' => ['owid.energy_supply_per_capita', 'إمداد الطاقة للفرد', 'Energy supply per capita', 'كيلوواط ساعة', 1_000_000],
        ];
    }

    protected function datasetKey(): string
    {
        return 'owid.energy';
    }

    protected function datasetNameAr(): string
    {
        return 'مزيج الكهرباء والكربون (Ember)';
    }

    protected function datasetNameEn(): string
    {
        return 'Electricity mix and carbon intensity (Ember)';
    }

    protected function section(): string
    {
        return 'energy';
    }
}
