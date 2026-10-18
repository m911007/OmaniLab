<?php

namespace App\Modules\Ncsi\Connectors;

use App\Core\Validation\Rules\Completeness;
use App\Core\Validation\Rules\Range;

/** الزراعة والثروة الحيوانية (وزارة الثروة الزراعية والسمكية والموارد المائية عبر NCSI): إنتاج المحاصيل ومساحتها وأعداد الحيوانات والمنتجات الحيوانية. */
final class NcsiAgricultureConnector extends KnoemaConnector
{
    protected function knoemaDatasetId(): string
    {
        return 'OMAGLS2016';
    }

    protected function datasetKey(): string
    {
        return 'ncsi.agriculture';
    }

    protected function datasetNameAr(): string
    {
        return 'عُمان: الزراعة والثروة الحيوانية';
    }

    protected function datasetNameEn(): string
    {
        return 'Oman agriculture and livestock';
    }

    protected function section(): string
    {
        return 'oman';
    }

    protected function intervalMinutes(): int
    {
        return 3 * 24 * 60;
    }

    protected function skipEmptySeries(): bool
    {
        return true;
    }

    protected function series(): array
    {
        $r = fn () => [new Range(0, 1e13, 'reject'), new Completeness(0.1)];

        return [
            new KnoemaSeries('AGR_CROP_PROD', 'إنتاج المحاصيل — الإجمالي', 'Crop production — total', 'طن', 'A', 1000010, $r(), ['type-of-crops' => 1000000]),
            new KnoemaSeries('AGR_CROP_VEG', 'إنتاج الخضروات', 'Vegetable production', 'طن', 'A', 1000010, $r(), ['type-of-crops' => 1000010]),
            new KnoemaSeries('AGR_CROP_FRUIT', 'إنتاج الفواكه', 'Fruit production', 'طن', 'A', 1000010, $r(), ['type-of-crops' => 1000020]),
            new KnoemaSeries('AGR_CROP_FIELD', 'إنتاج المحاصيل الحقلية', 'Field crop production', 'طن', 'A', 1000010, $r(), ['type-of-crops' => 1000030]),
            new KnoemaSeries('AGR_CROP_FODDER', 'إنتاج المحاصيل العلفية المعمرة', 'Perennial fodder production', 'طن', 'A', 1000010, $r(), ['type-of-crops' => 1000040]),
            new KnoemaSeries('AGR_CULTIVATED_AREA', 'إجمالي المساحة المزروعة', 'Total cultivated area', 'فدان', 'A', 1000020, $r(), ['type-of-crops' => 1000000]),
            new KnoemaSeries('AGR_LIVESTOCK_TOTAL', 'إجمالي أعداد الحيوانات', 'Total livestock', 'ألف رأس', 'A', 1000150, $r(), ['kind-of-animals' => 1000000]),
            new KnoemaSeries('AGR_CATTLE', 'الأبقار', 'Cattle', 'ألف رأس', 'A', 1000150, $r(), ['kind-of-animals' => 1000010]),
            new KnoemaSeries('AGR_SHEEP', 'الأغنام', 'Sheep', 'ألف رأس', 'A', 1000150, $r(), ['kind-of-animals' => 1000020]),
            new KnoemaSeries('AGR_GOATS', 'الماعز', 'Goats', 'ألف رأس', 'A', 1000150, $r(), ['kind-of-animals' => 1000030]),
            new KnoemaSeries('AGR_CAMELS', 'الجمال', 'Camels', 'ألف رأس', 'A', 1000150, $r(), ['kind-of-animals' => 1000040]),
            new KnoemaSeries('AGR_RED_MEAT', 'إنتاج اللحوم الحمراء', 'Red meat production', 'طن', 'A', 1000180, $r(), ['type-of-meat-products' => 1000010]),
            new KnoemaSeries('AGR_MILK', 'إنتاج الألبان الطازجة', 'Fresh milk production', 'طن', 'A', 1000180, $r(), ['type-of-meat-products' => 1000020]),
            new KnoemaSeries('AGR_POULTRY_MEAT', 'إنتاج لحوم الدواجن', 'Poultry meat production', 'طن', 'A', 1000180, $r(), ['type-of-meat-products' => 1000030]),
        ];
    }
}
