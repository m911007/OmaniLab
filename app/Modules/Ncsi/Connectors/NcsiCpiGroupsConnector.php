<?php

namespace App\Modules\Ncsi\Connectors;

use App\Core\Validation\Rules\Completeness;
use App\Core\Validation\Rules\Range;

/**
 * الأرقام القياسية لأسعار المستهلك حسب مجموعات السلع الأربع والعشرين (غذاء، لحوم، خضروات، سكن، نقل…) وحسب المحافظة — شهري.
 * هذا أقرب ما يتاح رسميًا لـ«أسعار المنتجات»: مرصد الأسعار لهيئة حماية المستهلك لا يوفّر واجهة بيانات مفتوحة.
 */
final class NcsiCpiGroupsConnector extends KnoemaConnector
{
    protected function knoemaDatasetId(): string
    {
        return 'rauaefb';
    }

    protected function datasetKey(): string
    {
        return 'ncsi.cpi_groups';
    }

    protected function datasetNameAr(): string
    {
        return 'عُمان: أسعار المستهلك حسب المجموعة السلعية والمحافظة';
    }

    protected function datasetNameEn(): string
    {
        return 'Oman CPI by commodity group and governorate';
    }

    protected function section(): string
    {
        return 'oman';
    }

    protected function intervalMinutes(): int
    {
        return 24 * 60;
    }

    protected function skipEmptySeries(): bool
    {
        return true;
    }

    protected function series(): array
    {
        $idx = fn () => [new Range(10, 1000, 'reject'), new Completeness(0.1)];
        $infl = fn () => [new Range(-60, 200, 'reject'), new Completeness(0.1)];

        return [
            new KnoemaSeries('CPIG_ALL_INDEX', 'الرقم القياسي لأسعار جميع السلع (2018=100)', 'CPI — All items (2018=100)', 'نقطة', 'M', 1000050, $idx(), ['commodities' => 1000000, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_ALL_INFL', 'التضخم السنوي — جميع السلع', 'Inflation y/y — All items', '%', 'M', 1000000, $infl(), ['commodities' => 1000000, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_FOOD_BEV_INDEX', 'الرقم القياسي لأسعار المواد الغذائية والمشروبات غير الكحولية (2018=100)', 'CPI — Food and non-alcoholic beverages (2018=100)', 'نقطة', 'M', 1000050, $idx(), ['commodities' => 1000010, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_FOOD_BEV_INFL', 'التضخم السنوي — المواد الغذائية والمشروبات غير الكحولية', 'Inflation y/y — Food and non-alcoholic beverages', '%', 'M', 1000000, $infl(), ['commodities' => 1000010, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_FOOD_INDEX', 'الرقم القياسي لأسعار المواد الغذائية (2018=100)', 'CPI — Food (2018=100)', 'نقطة', 'M', 1000050, $idx(), ['commodities' => 1000020, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_FOOD_INFL', 'التضخم السنوي — المواد الغذائية', 'Inflation y/y — Food', '%', 'M', 1000000, $infl(), ['commodities' => 1000020, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_BREAD_INDEX', 'الرقم القياسي لأسعار الخبز والحبوب (2018=100)', 'CPI — Bread and cereals (2018=100)', 'نقطة', 'M', 1000050, $idx(), ['commodities' => 1000030, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_BREAD_INFL', 'التضخم السنوي — الخبز والحبوب', 'Inflation y/y — Bread and cereals', '%', 'M', 1000000, $infl(), ['commodities' => 1000030, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_MEAT_INDEX', 'الرقم القياسي لأسعار اللحوم (2018=100)', 'CPI — Meat (2018=100)', 'نقطة', 'M', 1000050, $idx(), ['commodities' => 1000040, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_MEAT_INFL', 'التضخم السنوي — اللحوم', 'Inflation y/y — Meat', '%', 'M', 1000000, $infl(), ['commodities' => 1000040, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_FISH_INDEX', 'الرقم القياسي لأسعار الأسماك (2018=100)', 'CPI — Fish (2018=100)', 'نقطة', 'M', 1000050, $idx(), ['commodities' => 1000050, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_FISH_INFL', 'التضخم السنوي — الأسماك', 'Inflation y/y — Fish', '%', 'M', 1000000, $infl(), ['commodities' => 1000050, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_DAIRY_INDEX', 'الرقم القياسي لأسعار الحليب والجبن والبيض (2018=100)', 'CPI — Milk, cheese and eggs (2018=100)', 'نقطة', 'M', 1000050, $idx(), ['commodities' => 1000060, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_DAIRY_INFL', 'التضخم السنوي — الحليب والجبن والبيض', 'Inflation y/y — Milk, cheese and eggs', '%', 'M', 1000000, $infl(), ['commodities' => 1000060, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_OILS_INDEX', 'الرقم القياسي لأسعار الزيوت والدهون (2018=100)', 'CPI — Oils and fats (2018=100)', 'نقطة', 'M', 1000050, $idx(), ['commodities' => 1000070, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_OILS_INFL', 'التضخم السنوي — الزيوت والدهون', 'Inflation y/y — Oils and fats', '%', 'M', 1000000, $infl(), ['commodities' => 1000070, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_FRUIT_INDEX', 'الرقم القياسي لأسعار الفواكه (2018=100)', 'CPI — Fruit (2018=100)', 'نقطة', 'M', 1000050, $idx(), ['commodities' => 1000080, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_FRUIT_INFL', 'التضخم السنوي — الفواكه', 'Inflation y/y — Fruit', '%', 'M', 1000000, $infl(), ['commodities' => 1000080, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_VEG_INDEX', 'الرقم القياسي لأسعار الخضروات (2018=100)', 'CPI — Vegetables (2018=100)', 'نقطة', 'M', 1000050, $idx(), ['commodities' => 1000090, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_VEG_INFL', 'التضخم السنوي — الخضروات', 'Inflation y/y — Vegetables', '%', 'M', 1000000, $infl(), ['commodities' => 1000090, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_SUGAR_INDEX', 'الرقم القياسي لأسعار السكر والمربى والعسل والحلويات (2018=100)', 'CPI — Sugar, jam, honey, confectionery (2018=100)', 'نقطة', 'M', 1000050, $idx(), ['commodities' => 1000100, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_SUGAR_INFL', 'التضخم السنوي — السكر والمربى والعسل والحلويات', 'Inflation y/y — Sugar, jam, honey, confectionery', '%', 'M', 1000000, $infl(), ['commodities' => 1000100, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_FOOD_OTHER_INDEX', 'الرقم القياسي لأسعار منتجات غذائية أخرى (2018=100)', 'CPI — Other food (2018=100)', 'نقطة', 'M', 1000050, $idx(), ['commodities' => 1000110, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_FOOD_OTHER_INFL', 'التضخم السنوي — منتجات غذائية أخرى', 'Inflation y/y — Other food', '%', 'M', 1000000, $infl(), ['commodities' => 1000110, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_NONALC_INDEX', 'الرقم القياسي لأسعار المشروبات غير الكحولية (2018=100)', 'CPI — Non-alcoholic beverages (2018=100)', 'نقطة', 'M', 1000050, $idx(), ['commodities' => 1000120, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_NONALC_INFL', 'التضخم السنوي — المشروبات غير الكحولية', 'Inflation y/y — Non-alcoholic beverages', '%', 'M', 1000000, $infl(), ['commodities' => 1000120, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_TOBACCO_INDEX', 'الرقم القياسي لأسعار التبغ (2018=100)', 'CPI — Tobacco (2018=100)', 'نقطة', 'M', 1000050, $idx(), ['commodities' => 1000130, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_TOBACCO_INFL', 'التضخم السنوي — التبغ', 'Inflation y/y — Tobacco', '%', 'M', 1000000, $infl(), ['commodities' => 1000130, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_CLOTHING_INDEX', 'الرقم القياسي لأسعار الملابس والأحذية (2018=100)', 'CPI — Clothing and footwear (2018=100)', 'نقطة', 'M', 1000050, $idx(), ['commodities' => 1000140, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_CLOTHING_INFL', 'التضخم السنوي — الملابس والأحذية', 'Inflation y/y — Clothing and footwear', '%', 'M', 1000000, $infl(), ['commodities' => 1000140, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_HOUSING_INDEX', 'الرقم القياسي لأسعار السكن والمياه والكهرباء والغاز والوقود (2018=100)', 'CPI — Housing, water, electricity, gas (2018=100)', 'نقطة', 'M', 1000050, $idx(), ['commodities' => 1000150, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_HOUSING_INFL', 'التضخم السنوي — السكن والمياه والكهرباء والغاز والوقود', 'Inflation y/y — Housing, water, electricity, gas', '%', 'M', 1000000, $infl(), ['commodities' => 1000150, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_FURNISH_INDEX', 'الرقم القياسي لأسعار الأثاث والتجهيزات المنزلية (2018=100)', 'CPI — Furnishings and household equipment (2018=100)', 'نقطة', 'M', 1000050, $idx(), ['commodities' => 1000160, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_FURNISH_INFL', 'التضخم السنوي — الأثاث والتجهيزات المنزلية', 'Inflation y/y — Furnishings and household equipment', '%', 'M', 1000000, $infl(), ['commodities' => 1000160, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_HEALTH_INDEX', 'الرقم القياسي لأسعار الصحة (2018=100)', 'CPI — Health (2018=100)', 'نقطة', 'M', 1000050, $idx(), ['commodities' => 1000170, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_HEALTH_INFL', 'التضخم السنوي — الصحة', 'Inflation y/y — Health', '%', 'M', 1000000, $infl(), ['commodities' => 1000170, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_TRANSPORT_INDEX', 'الرقم القياسي لأسعار النقل (2018=100)', 'CPI — Transport (2018=100)', 'نقطة', 'M', 1000050, $idx(), ['commodities' => 1000180, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_TRANSPORT_INFL', 'التضخم السنوي — النقل', 'Inflation y/y — Transport', '%', 'M', 1000000, $infl(), ['commodities' => 1000180, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_COMM_INDEX', 'الرقم القياسي لأسعار الاتصالات (2018=100)', 'CPI — Communication (2018=100)', 'نقطة', 'M', 1000050, $idx(), ['commodities' => 1000190, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_COMM_INFL', 'التضخم السنوي — الاتصالات', 'Inflation y/y — Communication', '%', 'M', 1000000, $infl(), ['commodities' => 1000190, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_RECREATION_INDEX', 'الرقم القياسي لأسعار الثقافة والترفيه (2018=100)', 'CPI — Recreation and culture (2018=100)', 'نقطة', 'M', 1000050, $idx(), ['commodities' => 1000200, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_RECREATION_INFL', 'التضخم السنوي — الثقافة والترفيه', 'Inflation y/y — Recreation and culture', '%', 'M', 1000000, $infl(), ['commodities' => 1000200, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_EDU_INDEX', 'الرقم القياسي لأسعار التعليم (2018=100)', 'CPI — Education (2018=100)', 'نقطة', 'M', 1000050, $idx(), ['commodities' => 1000210, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_EDU_INFL', 'التضخم السنوي — التعليم', 'Inflation y/y — Education', '%', 'M', 1000000, $infl(), ['commodities' => 1000210, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_RESTAURANT_INDEX', 'الرقم القياسي لأسعار المطاعم والفنادق (2018=100)', 'CPI — Restaurants and hotels (2018=100)', 'نقطة', 'M', 1000050, $idx(), ['commodities' => 1000220, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_RESTAURANT_INFL', 'التضخم السنوي — المطاعم والفنادق', 'Inflation y/y — Restaurants and hotels', '%', 'M', 1000000, $infl(), ['commodities' => 1000220, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_MISC_INDEX', 'الرقم القياسي لأسعار السلع الشخصية والخدمات المتنوعة (2018=100)', 'CPI — Personal care and misc (2018=100)', 'نقطة', 'M', 1000050, $idx(), ['commodities' => 1000230, 'regions' => 1000000]),
            new KnoemaSeries('CPIG_MISC_INFL', 'التضخم السنوي — السلع الشخصية والخدمات المتنوعة', 'Inflation y/y — Personal care and misc', '%', 'M', 1000000, $infl(), ['commodities' => 1000230, 'regions' => 1000000]),
            new KnoemaSeries('CPIR_MUSCAT_INDEX', 'الرقم القياسي لأسعار المستهلك — مسقط', 'CPI — Muscat', 'نقطة', 'M', 1000050, $idx(), ['commodities' => 1000000, 'regions' => 1000010]),
            new KnoemaSeries('CPIR_MUSCAT_INFL', 'التضخم السنوي — مسقط', 'Inflation y/y — Muscat', '%', 'M', 1000000, $infl(), ['commodities' => 1000000, 'regions' => 1000010]),
            new KnoemaSeries('CPIR_DHOFAR_INDEX', 'الرقم القياسي لأسعار المستهلك — ظفار', 'CPI — Dhofar', 'نقطة', 'M', 1000050, $idx(), ['commodities' => 1000000, 'regions' => 1000080]),
            new KnoemaSeries('CPIR_DHOFAR_INFL', 'التضخم السنوي — ظفار', 'Inflation y/y — Dhofar', '%', 'M', 1000000, $infl(), ['commodities' => 1000000, 'regions' => 1000080]),
            new KnoemaSeries('CPIR_MUSANDAM_INDEX', 'الرقم القياسي لأسعار المستهلك — مسندم', 'CPI — Musandam', 'نقطة', 'M', 1000050, $idx(), ['commodities' => 1000000, 'regions' => 1000190]),
            new KnoemaSeries('CPIR_MUSANDAM_INFL', 'التضخم السنوي — مسندم', 'Inflation y/y — Musandam', '%', 'M', 1000000, $infl(), ['commodities' => 1000000, 'regions' => 1000190]),
            new KnoemaSeries('CPIR_BURAIMI_INDEX', 'الرقم القياسي لأسعار المستهلك — البريمي', 'CPI — Buraimi', 'نقطة', 'M', 1000050, $idx(), ['commodities' => 1000000, 'regions' => 1000240]),
            new KnoemaSeries('CPIR_BURAIMI_INFL', 'التضخم السنوي — البريمي', 'Inflation y/y — Buraimi', '%', 'M', 1000000, $infl(), ['commodities' => 1000000, 'regions' => 1000240]),
            new KnoemaSeries('CPIR_DAKHILIYAH_INDEX', 'الرقم القياسي لأسعار المستهلك — الداخلية', 'CPI — Ad Dakhiliyah', 'نقطة', 'M', 1000050, $idx(), ['commodities' => 1000000, 'regions' => 1000280]),
            new KnoemaSeries('CPIR_DAKHILIYAH_INFL', 'التضخم السنوي — الداخلية', 'Inflation y/y — Ad Dakhiliyah', '%', 'M', 1000000, $infl(), ['commodities' => 1000000, 'regions' => 1000280]),
            new KnoemaSeries('CPIR_N_BATINAH_INDEX', 'الرقم القياسي لأسعار المستهلك — شمال الباطنة', 'CPI — North Al Batinah', 'نقطة', 'M', 1000050, $idx(), ['commodities' => 1000000, 'regions' => 1000390]),
            new KnoemaSeries('CPIR_N_BATINAH_INFL', 'التضخم السنوي — شمال الباطنة', 'Inflation y/y — North Al Batinah', '%', 'M', 1000000, $infl(), ['commodities' => 1000000, 'regions' => 1000390]),
            new KnoemaSeries('CPIR_S_BATINAH_INDEX', 'الرقم القياسي لأسعار المستهلك — جنوب الباطنة', 'CPI — South Al Batinah', 'نقطة', 'M', 1000050, $idx(), ['commodities' => 1000000, 'regions' => 1000460]),
            new KnoemaSeries('CPIR_S_BATINAH_INFL', 'التضخم السنوي — جنوب الباطنة', 'Inflation y/y — South Al Batinah', '%', 'M', 1000000, $infl(), ['commodities' => 1000000, 'regions' => 1000460]),
            new KnoemaSeries('CPIR_S_SHARQIYAH_INDEX', 'الرقم القياسي لأسعار المستهلك — جنوب الشرقية', 'CPI — South Al Sharqiyah', 'نقطة', 'M', 1000050, $idx(), ['commodities' => 1000000, 'regions' => 1000540]),
            new KnoemaSeries('CPIR_S_SHARQIYAH_INFL', 'التضخم السنوي — جنوب الشرقية', 'Inflation y/y — South Al Sharqiyah', '%', 'M', 1000000, $infl(), ['commodities' => 1000000, 'regions' => 1000540]),
            new KnoemaSeries('CPIR_N_SHARQIYAH_INDEX', 'الرقم القياسي لأسعار المستهلك — شمال الشرقية', 'CPI — North Al Sharqiyah', 'نقطة', 'M', 1000050, $idx(), ['commodities' => 1000000, 'regions' => 1000600]),
            new KnoemaSeries('CPIR_N_SHARQIYAH_INFL', 'التضخم السنوي — شمال الشرقية', 'Inflation y/y — North Al Sharqiyah', '%', 'M', 1000000, $infl(), ['commodities' => 1000000, 'regions' => 1000600]),
            new KnoemaSeries('CPIR_DHAHIRAH_INDEX', 'الرقم القياسي لأسعار المستهلك — الظاهرة', 'CPI — Ad Dhahirah', 'نقطة', 'M', 1000050, $idx(), ['commodities' => 1000000, 'regions' => 1000680]),
            new KnoemaSeries('CPIR_DHAHIRAH_INFL', 'التضخم السنوي — الظاهرة', 'Inflation y/y — Ad Dhahirah', '%', 'M', 1000000, $infl(), ['commodities' => 1000000, 'regions' => 1000680]),
            new KnoemaSeries('CPIR_WUSTA_INDEX', 'الرقم القياسي لأسعار المستهلك — الوسطى', 'CPI — Al Wusta', 'نقطة', 'M', 1000050, $idx(), ['commodities' => 1000000, 'regions' => 1000720]),
            new KnoemaSeries('CPIR_WUSTA_INFL', 'التضخم السنوي — الوسطى', 'Inflation y/y — Al Wusta', '%', 'M', 1000000, $infl(), ['commodities' => 1000000, 'regions' => 1000720]),
        ];
    }
}
