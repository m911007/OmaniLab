<?php

/**
 * بوابة التنمية العربية (data.arabdevelopmentportal.org): مجموعات تُجلب من واجهة api.arabdevelopmentportal.org/api/data (مفتوحة بلا مفتاح).
 * shape: wide = أعمدة سنوات (2010…)، long = صف لكل (دولة، مؤشر، سنة). dims = أعمدة تفصيل تُدمج في هوية السلسلة (تُتجاهل قيم NA/الفارغة).
 * هوية السلاسل تُلتقط في لقطة database/data/adp/<id>.json بالأمر adp:snapshot لأن تعريف المؤشرات يأتي من الكود لا من الشبكة.
 * تنبيه: robots.txt للموقع يشترط إذنًا كتابيًا للجمع الآلي؛ يُحدَّد المعدل بجلب أسبوعي واحد لكل مجموعة.
 */
return [
    'api' => 'https://api.arabdevelopmentportal.org/api/data/',
    'datasets' => [
        'environment' => [
            'name_ar' => 'الأسكوا: إحصاءات البيئة للدول العربية', 'name_en' => 'ESCWA: environment statistics (Arab states)',
            'uuid' => '064bee84-a251-51d4-bab3-709b0dcb9b1a', 'organization' => 'unagencies', 'source' => 'escwa',
            'shape' => 'long', 'country' => 'Country_ISO_3', 'country_kind' => 'iso3', 'indicator' => 'Indicator_Name', 'unit' => 'Unit_Name', 'dims' => [], 'time' => 'Time', 'value' => 'Value',
            'section' => 'energy',
        ],
        'labour' => [
            'name_ar' => 'الأسكوا: مؤشرات القوى العاملة (مواطنون ووافدون)', 'name_en' => 'ESCWA: labour force indicators by nationality',
            'uuid' => 'c3b3d0cb-a3cf-55cf-a723-20b85d986d27', 'organization' => 'unagencies', 'source' => 'escwa',
            'shape' => 'wide', 'country' => 'Country', 'country_kind' => 'name', 'indicator' => 'Indicator_Name', 'unit' => null,
            'dims' => ['Sex', 'Age', 'Nationality', 'Reasons_for_inactivity', 'Employment_status', 'Economic_activity', 'Sector', 'Main_occupation'], 'time' => null, 'value' => null,
            'section' => 'society',
        ],
        'population' => [
            'name_ar' => 'الأسكوا: السكان والتركيبة الاجتماعية', 'name_en' => 'ESCWA: population and social structure',
            'uuid' => '37c46c57-e009-57f1-9d89-243d2e4d3ba4', 'organization' => 'unagencies', 'source' => 'escwa',
            'shape' => 'wide', 'country' => 'Country_Name', 'country_kind' => 'name', 'indicator' => 'Indicator_Name', 'unit' => null,
            'dims' => ['Age', 'Sex', 'Area', 'Nationality', 'Marital_status', 'Causes_of_death'], 'time' => null, 'value' => null,
            'section' => 'society',
        ],
    ],
    'arab_countries' => [
        'Algeria' => 'DZA', 'Bahrain' => 'BHR', 'Comoros' => 'COM', 'Djibouti' => 'DJI', 'Egypt' => 'EGY', 'Iraq' => 'IRQ', 'Jordan' => 'JOR', 'Kuwait' => 'KWT',
        'Lebanon' => 'LBN', 'Libya' => 'LBY', 'Mauritania' => 'MRT', 'Morocco' => 'MAR', 'Oman' => 'OMN', 'Palestine' => 'PSE', 'State of Palestine' => 'PSE', 'Qatar' => 'QAT',
        'Saudi Arabia' => 'SAU', 'Somalia' => 'SOM', 'Sudan' => 'SDN', 'Syria' => 'SYR', 'Syrian Arab Republic' => 'SYR', 'Tunisia' => 'TUN', 'United Arab Emirates' => 'ARE', 'Yemen' => 'YEM',
    ],
];
