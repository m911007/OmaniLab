<?php

/**
 * المؤسسات والمرافق والموارد المُستخرجة من OpenStreetMap (Overpass) لعُمان.
 * group: government | education | health | resources. filter: عبارات Overpass تُضاف إلى نطاق عُمان.
 * required_name: المواقع بلا اسم تُتخطى (لا يمكن تعريفها)، إلا للبنى التحتية الكبرى التي قد تكون بلا اسم.
 */
return [
    'groups' => [
        'government' => 'المؤسسات الحكومية',
        'education' => 'المؤسسات التعليمية',
        'health' => 'المؤسسات الصحية',
        'resources' => 'الموارد الطبيعية والبنية التحتية',
        'industry' => 'المصانع والمناطق الصناعية والإنتاج',
    ],
    'categories' => [
        'gov_office' => ['group' => 'government', 'ar' => 'دوائر ومكاتب حكومية', 'en' => 'Government offices', 'filter' => ['["office"="government"]']],
        'townhall' => ['group' => 'government', 'ar' => 'مقار الولايات والبلديات', 'en' => 'Town halls', 'filter' => ['["amenity"="townhall"]']],
        'courthouse' => ['group' => 'government', 'ar' => 'المحاكم', 'en' => 'Courthouses', 'filter' => ['["amenity"="courthouse"]']],
        'police' => ['group' => 'government', 'ar' => 'مراكز الشرطة', 'en' => 'Police stations', 'filter' => ['["amenity"="police"]']],
        'fire_station' => ['group' => 'government', 'ar' => 'مراكز الدفاع المدني', 'en' => 'Fire stations', 'filter' => ['["amenity"="fire_station"]']],
        'post_office' => ['group' => 'government', 'ar' => 'مكاتب البريد', 'en' => 'Post offices', 'filter' => ['["amenity"="post_office"]']],
        'embassy' => ['group' => 'government', 'ar' => 'السفارات والقنصليات', 'en' => 'Embassies and consulates', 'filter' => ['["office"="diplomatic"]', '["amenity"="embassy"]']],
        'university' => ['group' => 'education', 'ar' => 'الجامعات', 'en' => 'Universities', 'filter' => ['["amenity"="university"]']],
        'college' => ['group' => 'education', 'ar' => 'الكليات والمعاهد', 'en' => 'Colleges and institutes', 'filter' => ['["amenity"="college"]']],
        'school' => ['group' => 'education', 'ar' => 'المدارس', 'en' => 'Schools', 'filter' => ['["amenity"="school"]']],
        'kindergarten' => ['group' => 'education', 'ar' => 'رياض الأطفال', 'en' => 'Kindergartens', 'filter' => ['["amenity"="kindergarten"]']],
        'library' => ['group' => 'education', 'ar' => 'المكتبات', 'en' => 'Libraries', 'filter' => ['["amenity"="library"]']],
        'research' => ['group' => 'education', 'ar' => 'مراكز البحث', 'en' => 'Research centres', 'filter' => ['["office"="research"]', '["amenity"="research_institute"]']],
        'hospital' => ['group' => 'health', 'ar' => 'المستشفيات', 'en' => 'Hospitals', 'filter' => ['["amenity"="hospital"]']],
        'clinic' => ['group' => 'health', 'ar' => 'المراكز والعيادات الصحية', 'en' => 'Clinics', 'filter' => ['["amenity"="clinic"]', '["healthcare"="centre"]']],
        'power_plant' => ['group' => 'resources', 'ar' => 'محطات توليد الكهرباء', 'en' => 'Power plants', 'filter' => ['["power"="plant"]'], 'allow_unnamed' => true],
        'solar_farm' => ['group' => 'resources', 'ar' => 'محطات الطاقة الشمسية', 'en' => 'Solar farms', 'filter' => ['["power"="plant"]["plant:source"="solar"]', '["power"="generator"]["generator:source"="solar"]["name"]'], 'allow_unnamed' => true],
        'wind_farm' => ['group' => 'resources', 'ar' => 'محطات طاقة الرياح', 'en' => 'Wind farms', 'filter' => ['["power"="plant"]["plant:source"="wind"]', '["power"="generator"]["generator:source"="wind"]["name"]'], 'allow_unnamed' => true],
        'desalination' => ['group' => 'resources', 'ar' => 'محطات تحلية المياه', 'en' => 'Desalination plants', 'filter' => ['["man_made"="desalination_plant"]', '["man_made"="water_works"]["water_works"="desalination"]', '["man_made"="water_works"]["name"~"desal|تحلية",i]', '["power"="plant"]["name"~"desal|تحلية",i]'], 'allow_unnamed' => true],
        'dam' => ['group' => 'resources', 'ar' => 'السدود', 'en' => 'Dams', 'filter' => ['["waterway"="dam"]', '["man_made"="dam"]'], 'allow_unnamed' => true],
        'oil_gas' => ['group' => 'resources', 'ar' => 'مرافق وآبار النفط والغاز', 'en' => 'Oil and gas facilities', 'filter' => ['["industrial"~"^(oil|gas|refinery)$"]', '["man_made"~"^(petroleum_well|petroleum_terminal)$"]'], 'allow_unnamed' => true],
        'mine' => ['group' => 'resources', 'ar' => 'المحاجر والمناجم', 'en' => 'Quarries and mines', 'filter' => ['["landuse"="quarry"]', '["industrial"="mine"]'], 'allow_unnamed' => true],
        'port' => ['group' => 'resources', 'ar' => 'الموانئ', 'en' => 'Ports', 'filter' => ['["landuse"="port"]', '["harbour"="yes"]', '["industrial"="port"]']],
        'airport' => ['group' => 'resources', 'ar' => 'المطارات', 'en' => 'Airports', 'filter' => ['["aeroway"="aerodrome"]']],
        'protected_area' => ['group' => 'resources', 'ar' => 'المحميات الطبيعية', 'en' => 'Protected areas', 'filter' => ['["boundary"="protected_area"]', '["boundary"="national_park"]', '["leisure"="nature_reserve"]']],
        'factory' => ['group' => 'industry', 'ar' => 'المصانع والمنشآت الإنتاجية', 'en' => 'Factories and works', 'filter' => ['["man_made"="works"]', '["industrial"="factory"]', '["building"="industrial"]["name"]']],
        'industrial_zone' => ['group' => 'industry', 'ar' => 'المناطق الصناعية', 'en' => 'Industrial zones', 'filter' => ['["landuse"="industrial"]["name"]', '["industrial"="industrial_park"]']],
        'heavy_industry' => ['group' => 'industry', 'ar' => 'الصناعات الثقيلة (إسمنت، أسمدة، كيماويات، حديد، ألمنيوم)', 'en' => 'Heavy industry', 'filter' => ['["industrial"~"^(cement|chemical|fertilizer|steel|aluminium|metal)$"]'], 'allow_unnamed' => true],
        'fish_farm' => ['group' => 'industry', 'ar' => 'مزارع الاستزراع السمكي', 'en' => 'Aquaculture farms', 'filter' => ['["landuse"="aquaculture"]', '["man_made"="fish_farm"]'], 'allow_unnamed' => true],
        'farm' => ['group' => 'industry', 'ar' => 'المزارع ومشاريع الإنتاج الزراعي', 'en' => 'Farms', 'filter' => ['["landuse"="farmyard"]["name"]', '["place"="farm"]', '["industrial"="agricultural"]']],
        'livestock' => ['group' => 'industry', 'ar' => 'مزارع الماشية والدواجن', 'en' => 'Livestock and poultry farms', 'filter' => ['["building"="barn"]["name"]', '["building"="stable"]["name"]', '["industrial"="livestock"]']],
    ],
];
