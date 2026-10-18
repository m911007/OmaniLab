<?php

namespace Tests\Feature;

use App\Modules\Adp\AdpCatalog;
use Tests\TestCase;

class AdpCatalogTest extends TestCase
{
    public function test_wide_rows_become_one_series_per_dimension_combination_with_year_values(): void
    {
        config(['adp.datasets.t' => ['shape' => 'wide', 'country' => 'Country', 'country_kind' => 'name', 'indicator' => 'Indicator_Name', 'unit' => null, 'dims' => ['Sex', 'Nationality'], 'time' => null, 'value' => null]]);
        $catalog = new AdpCatalog(config('adp.datasets.t'));
        $rows = [
            ['Indicator_Name' => 'Unemployment rate', 'Country' => 'Oman', 'Sex' => 'Both sexes', 'Nationality' => 'Nationals', 2023 => '3.1', 2022 => '', 2021 => '4.2'],
            ['Indicator_Name' => 'Unemployment rate', 'Country' => 'Atlantis', 'Sex' => 'Sex NA', 'Nationality' => '', 2023 => '9'],
        ];

        $out = iterator_to_array($catalog->observations($rows), false);

        $this->assertSame([['OMN', 'Unemployment rate — Both sexes — Nationals', null, '2023', 3.1], ['OMN', 'Unemployment rate — Both sexes — Nationals', null, '2021', 4.2]], $out);
    }

    public function test_long_rows_use_iso3_country_unit_and_time_columns(): void
    {
        config(['adp.datasets.t' => ['shape' => 'long', 'country' => 'Country_ISO_3', 'country_kind' => 'iso3', 'indicator' => 'Indicator_Name', 'unit' => 'Unit_Name', 'dims' => [], 'time' => 'Time', 'value' => 'Value']]);
        $catalog = new AdpCatalog(config('adp.datasets.t'));

        $out = iterator_to_array($catalog->observations([['Country_ISO_3' => 'KWT', 'Indicator_Name' => 'Waste', 'Unit_Name' => 'Tons', 'Time' => 2014, 'Value' => 12.5], ['Country_ISO_3' => '', 'Indicator_Name' => 'Waste', 'Unit_Name' => 'Tons', 'Time' => 2014, 'Value' => 1]]), false);

        $this->assertSame([['KWT', 'Waste', 'Tons', '2014', 12.5]], $out);
        $this->assertSame($catalog->seriesId('x', 'Waste', 'Tons'), $catalog->seriesId('x', 'Waste', 'Tons'));
    }
}
