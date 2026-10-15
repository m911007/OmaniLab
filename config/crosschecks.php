<?php

/**
 * القياسات التي تنشرها عدة مصادر: تُقارَن قيمها على الفترات المشتركة، فيظهر للقارئ هل المصادر متطابقة أم مختلفة.
 * members: مؤشرات متكافئة؛ scale = معامل التحويل إلى وحدة المجموعة (الوحدة المعيارية = وحدة أول عضو، scale 1).
 * mode: relative (فرق نسبي من الوسيط) أو absolute (فرق بوحدة المؤشر، للنسب). tolerance: أقصى فرق يُعدّ تطابقًا.
 * يُقارَن آخر ما يتوافر لمصدرين فأكثر من السنوات الفعلية (حتى السنة الماضية).
 */
return [
    'compare_periods' => 3,
    'groups' => [
        ['key' => 'gdp_usd', 'label' => 'الناتج المحلي الإجمالي (بالدولار)', 'mode' => 'relative', 'tolerance' => 0.03, 'members' => [
            ['indicator' => 'imf.NGDPD', 'scale' => 1],
            ['indicator' => 'wb.NY.GDP.MKTP.CD', 'scale' => 1e-9],
            ['indicator' => 'ncsi.GDP_CURRENT', 'scale' => 0.0026008], // مليون ريال × 2.6008 (الربط الثابت) ÷ 1000 = مليار دولار
        ]],
        ['key' => 'gdp_per_capita', 'label' => 'نصيب الفرد من الناتج', 'mode' => 'relative', 'tolerance' => 0.03, 'members' => [
            ['indicator' => 'imf.NGDPDPC', 'scale' => 1],
            ['indicator' => 'wb.NY.GDP.PCAP.CD', 'scale' => 1],
            ['indicator' => 'ncsi.GDP_PER_CAPITA_USD', 'scale' => 1],
        ]],
        ['key' => 'inflation', 'label' => 'التضخم السنوي', 'mode' => 'absolute', 'tolerance' => 0.3, 'members' => [
            ['indicator' => 'imf.PCPIPCH', 'scale' => 1],
            ['indicator' => 'wb.FP.CPI.TOTL.ZG', 'scale' => 1],
        ]],
        ['key' => 'population', 'label' => 'عدد السكان', 'mode' => 'relative', 'tolerance' => 0.02, 'members' => [
            ['indicator' => 'wb.SP.POP.TOTL', 'scale' => 1],
            ['indicator' => 'imf.LP', 'scale' => 1e6],
            ['indicator' => 'gccstat.POP', 'scale' => 1],
            ['indicator' => 'wbp.SP.POP.TOTL', 'scale' => 1],
        ]],
        ['key' => 'unemployment', 'label' => 'معدل البطالة', 'mode' => 'absolute', 'tolerance' => 0.5, 'members' => [
            ['indicator' => 'ilo.unemployment', 'scale' => 1],
            ['indicator' => 'wb.SL.UEM.TOTL.ZS', 'scale' => 1],
            ['indicator' => 'imf.LUR', 'scale' => 1],
            ['indicator' => 'gccstat.UNEMPLOYMENT', 'scale' => 1],
        ]],
        ['key' => 'youth_unemployment', 'label' => 'بطالة الشباب', 'mode' => 'absolute', 'tolerance' => 1.5, 'members' => [
            ['indicator' => 'ilo.unemployment_youth', 'scale' => 1],
            ['indicator' => 'wb.SL.UEM.1524.ZS', 'scale' => 1],
        ]],
        ['key' => 'current_account', 'label' => 'رصيد الحساب الجاري (% من الناتج)', 'mode' => 'absolute', 'tolerance' => 0.5, 'members' => [
            ['indicator' => 'imf.BCA_NGDPD', 'scale' => 1],
            ['indicator' => 'wb.BN.CAB.XOKA.GD.ZS', 'scale' => 1],
        ]],
        ['key' => 'life_expectancy', 'label' => 'العمر المتوقع عند الولادة', 'mode' => 'absolute', 'tolerance' => 0.5, 'members' => [
            ['indicator' => 'undp.le', 'scale' => 1],
            ['indicator' => 'wb.SP.DYN.LE00.IN', 'scale' => 1],
        ]],
        ['key' => 'renewable_electricity', 'label' => 'حصة المتجددة من الكهرباء', 'mode' => 'absolute', 'tolerance' => 1.0, 'members' => [
            ['indicator' => 'owid.renewable_electricity_share', 'scale' => 1],
            ['indicator' => 'wb.EG.ELC.RNEW.ZS', 'scale' => 1],
        ]],
        ['key' => 'doctors', 'label' => 'الأطباء لكل 1000 نسمة', 'mode' => 'absolute', 'tolerance' => 0.3, 'members' => [
            ['indicator' => 'wb.SH.MED.PHYS.ZS', 'scale' => 1],
            ['indicator' => 'who.doctors', 'scale' => 0.1],
        ]],
        ['key' => 'edu_spend', 'label' => 'الإنفاق الحكومي على التعليم (% من الناتج)', 'mode' => 'absolute', 'tolerance' => 0.3, 'members' => [
            ['indicator' => 'unesco.edu_spend_gdp', 'scale' => 1],
            ['indicator' => 'wb.SE.XPD.TOTL.GD.ZS', 'scale' => 1],
        ]],
        ['key' => 'tertiary_enrolment', 'label' => 'الالتحاق بالتعليم العالي', 'mode' => 'absolute', 'tolerance' => 3.0, 'members' => [
            ['indicator' => 'unesco.ger_tertiary', 'scale' => 1],
            ['indicator' => 'wb.SE.TER.ENRR', 'scale' => 1],
        ]],
        ['key' => 'secondary_enrolment', 'label' => 'الالتحاق بالتعليم الثانوي', 'mode' => 'absolute', 'tolerance' => 3.0, 'members' => [
            ['indicator' => 'unesco.ger_secondary', 'scale' => 1],
            ['indicator' => 'wb.SE.SEC.ENRR', 'scale' => 1],
        ]],
        ['key' => 'primary_enrolment', 'label' => 'الالتحاق بالتعليم الابتدائي', 'mode' => 'absolute', 'tolerance' => 3.0, 'members' => [
            ['indicator' => 'unesco.ger_primary', 'scale' => 1],
            ['indicator' => 'wb.SE.PRM.ENRR', 'scale' => 1],
        ]],
        ['key' => 'adult_literacy', 'label' => 'محو أمية الكبار', 'mode' => 'absolute', 'tolerance' => 1.5, 'members' => [
            ['indicator' => 'unesco.literacy_adult', 'scale' => 1],
            ['indicator' => 'wb.SE.ADT.LITR.ZS', 'scale' => 1],
        ]],
        ['key' => 'co2_per_capita', 'label' => 'انبعاثات CO₂ للفرد (إنتاج)', 'mode' => 'relative', 'tolerance' => 0.15, 'members' => [
            ['indicator' => 'undp.co2_prod', 'scale' => 1],
            ['indicator' => 'owid.co2_per_capita', 'scale' => 1],
        ]],
    ],
];
