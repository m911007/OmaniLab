<?php

namespace App\Modules\Gcc\Connectors;

/** المركز الإحصائي الخليجي (تفصيل إضافي): السكان حسب النوع والجنسية، المواليد والوفيات، الكوادر الصحية والتعليمية، الزراعة والثروة الحيوانية والأسماك، المياه والنفايات، الثقافة والسياحة، ومؤشرات دولية. */
final class GccStatMoreConnector extends GccStatConnector
{
    protected function datasetKey(): string
    {
        return 'gccstat.more';
    }

    protected function datasetNameAr(): string
    {
        return 'دول الخليج: تفاصيل السكان والزراعة والمياه والخدمات';
    }

    protected function datasetNameEn(): string
    {
        return 'GCC: population detail, agriculture, water and services';
    }

    protected function section(): string
    {
        return 'society';
    }

    protected function intervalMinutes(): int
    {
        return 3 * 24 * 60;
    }

    protected function flows(): array
    {
        $f = fn (string $flow, string $code, string $ar, string $en, string $unit) => GccFlow::level("MOBILE_DF_{$flow}", $code, $ar, $en, $unit);

        return [
            $f('POP_MALE', 'POP_MALE', 'السكان الذكور', 'Male population', 'نسمة'),
            $f('POP_FEMALE', 'POP_FEMALE', 'السكان الإناث', 'Female population', 'نسمة'),
            $f('POP_CITIZEN_MALE', 'POP_CITIZEN_MALE', 'المواطنون الذكور', 'Citizen male population', 'نسمة'),
            $f('POP_CITIZEN_FEMALE', 'POP_CITIZEN_FEMALE', 'المواطنات', 'Citizen female population', 'نسمة'),
            $f('POP_NON_CITIZEN_MALE', 'POP_NON_CITIZEN_MALE', 'غير المواطنين الذكور', 'Non-citizen male population', 'نسمة'),
            $f('POP_NON_CITIZEN_FEMALE', 'POP_NON_CITIZEN_FEMALE', 'غير المواطنات', 'Non-citizen female population', 'نسمة'),
            $f('BTH', 'BIRTHS', 'عدد المواليد', 'Births', 'مولود'),
            $f('BTH_CITIZEN', 'BIRTHS_CITIZEN', 'مواليد المواطنين', 'Citizen births', 'مولود'),
            $f('DTH', 'DEATHS', 'عدد الوفيات', 'Deaths', 'حالة'),
            $f('DTH_CITIZEN', 'DEATHS_CITIZEN', 'وفيات المواطنين', 'Citizen deaths', 'حالة'),
            $f('LAB_EMPLOYEES_FEMALE', 'EMPLOYED_FEMALE', 'العاملات', 'Female employees', 'شخص'),
            $f('LAB_EMPLOYEES_MALE', 'EMPLOYED_MALE', 'العاملون الذكور', 'Male employees', 'شخص'),
            $f('EDU_TEACHER_EDU', 'TEACHERS_SCHOOL', 'معلمو التعليم المدرسي', 'School teachers', 'معلم'),
            $f('EDU_TEACHER_UNI', 'TEACHERS_UNI', 'أعضاء هيئة التدريس الجامعي', 'Higher-education teachers', 'عضو'),
            $f('HTH_DENTISTS', 'DENTISTS', 'أطباء الأسنان', 'Dentists', 'طبيب'),
            $f('HTH_PHARMASISTS', 'PHARMACISTS', 'الصيادلة', 'Pharmacists', 'صيدلي'),
            $f('AGR_Dates', 'AGR_DATES', 'إنتاج التمور', 'Dates production', 'طن'),
            $f('AGR_Cereals', 'AGR_CEREALS', 'إنتاج الحبوب', 'Cereals production', 'طن'),
            $f('AGR_Fruits', 'AGR_FRUITS', 'إنتاج الفاكهة', 'Fruits production', 'طن'),
            $f('AGR_Vegetables', 'AGR_VEGETABLES', 'إنتاج الخضروات', 'Vegetables production', 'طن'),
            $f('AGR_Eggs', 'AGR_EGGS', 'إنتاج البيض', 'Eggs production', 'طن'),
            $f('AGR_Poultry_Meat', 'AGR_POULTRY', 'إنتاج لحوم الدواجن', 'Poultry meat production', 'طن'),
            $f('AGR_Camels', 'AGR_CAMELS', 'عدد الإبل', 'Camels', 'رأس'),
            $f('AGR_Sheeps', 'AGR_SHEEP', 'عدد الأغنام', 'Sheep', 'رأس'),
            $f('AGR_Gotas', 'AGR_GOATS', 'عدد الماعز', 'Goats', 'رأس'),
            $f('AGR_Cows', 'AGR_COWS', 'عدد الأبقار', 'Cows', 'رأس'),
            $f('AGR_Fish_Caught', 'FISH_CAUGHT', 'كمية الأسماك المصيدة', 'Fish caught', 'طن'),
            $f('AGR_Fishers', 'FISHERS', 'عدد الصيادين', 'Fishers', 'صياد'),
            $f('AGR_Fishing_Boats', 'FISHING_BOATS', 'عدد قوارب الصيد', 'Fishing boats', 'قارب'),
            $f('AGR_Arable_Land', 'ARABLE_LAND', 'الأراضي الصالحة للزراعة', 'Arable land', 'هكتار'),
            $f('AGR_Cultivated_Land', 'CULTIVATED_LAND', 'الأراضي المزروعة', 'Cultivated land', 'هكتار'),
            $f('WTR_REUSED_WATER', 'WATER_REUSED', 'المياه المعاد استخدامها', 'Reused water', 'م³'),
            $f('WTR_DES_P_CAP', 'WATER_DES_PER_CAPITA', 'المياه المحلاة للفرد', 'Desalinated water per capita', 'م³/فرد'),
            $f('CUL_MUES', 'MUSEUMS', 'عدد المتاحف', 'Museums', 'متحف'),
            $f('CUL_MUES_VIST', 'MUSEUM_VISITORS', 'زوار المتاحف', 'Museum visitors', 'زائر'),
            $f('CUL_HIST', 'HISTORIC_SITES', 'المواقع الأثرية والتاريخية', 'Archaeological and historical sites', 'موقع'),
            $f('WST_HAZ_COLLECTED', 'HAZ_WASTE_COLLECTED', 'النفايات الخطرة المجمَّعة', 'Hazardous waste collected', 'طن'),
            $f('WST_HAZ_TREATED', 'HAZ_WASTE_TREATED', 'النفايات الخطرة المعالجة', 'Hazardous waste treated', 'طن'),
            $f('WST_HAZ_MUN_HOUS', 'MUNICIPAL_WASTE_HOUSEHOLDS', 'النفايات البلدية من المنازل', 'Municipal waste from households', 'طن'),
            GccFlow::percent('MOBILE_DF_SDG07_7_1_1', 'ACCESS_ELECTRICITY', 'السكان المتصلون بالكهرباء', 'Population with access to electricity', 'A', 0, 100),
        ];
    }
}
