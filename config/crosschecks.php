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
        ['key' => 'gdp_per_capita_ppp', 'label' => 'نصيب الفرد بتعادل القوة الشرائية', 'mode' => 'relative', 'tolerance' => 0.05, 'members' => [
            ['indicator' => 'imf.PPPPC', 'scale' => 1],
            ['indicator' => 'wb.NY.GDP.PCAP.PP.CD', 'scale' => 1],
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
        ['key' => 'current_account_usd', 'label' => 'رصيد الحساب الجاري (مليار دولار)', 'mode' => 'absolute', 'tolerance' => 2.0, 'members' => [
            ['indicator' => 'imf.BCA', 'scale' => 1],
            ['indicator' => 'ncsi.BOP_CURRENT_ACCOUNT', 'scale' => 0.0026008],
        ]],
        ['key' => 'omani_workers', 'label' => 'العاملون العُمانيون', 'mode' => 'relative', 'tolerance' => 0.1, 'members' => [
            ['indicator' => 'ncsi.LAB_WORKERS_OMANI', 'scale' => 1],
            ['indicator' => 'ilo.emp_citizen', 'scale' => 1],
        ]],
        ['key' => 'expat_workers', 'label' => 'العاملون الوافدون', 'mode' => 'relative', 'tolerance' => 0.1, 'members' => [
            ['indicator' => 'ncsi.LAB_WORKERS_EXPAT', 'scale' => 1],
            ['indicator' => 'ilo.emp_noncitizen', 'scale' => 1],
        ]],
        ['key' => 'female_lfp', 'label' => 'مشاركة المرأة في القوى العاملة', 'mode' => 'absolute', 'tolerance' => 3.0, 'members' => [
            ['indicator' => 'ilo.lfp_female', 'scale' => 1],
            ['indicator' => 'wb.SL.TLF.CACT.FE.ZS', 'scale' => 1],
        ]],
        ['key' => 'govt_revenue', 'label' => 'الإيرادات الحكومية (% من الناتج)', 'mode' => 'absolute', 'tolerance' => 1.5, 'members' => [
            ['indicator' => 'imf.GGR_G01_GDP_PT', 'scale' => 1],
            ['indicator' => 'imf.rev', 'scale' => 1],
            ['indicator' => 'wb.GC.TAX.TOTL.GD.ZS', 'scale' => 1],
        ]],
        ['key' => 'inflation_monthly', 'label' => 'التضخم السنوي (شهري)', 'frequency' => 'M', 'mode' => 'absolute', 'tolerance' => 0.3, 'members' => [
            ['indicator' => 'imf.cpi_yoy', 'scale' => 1],
            ['indicator' => 'ncsi.CPI_INFLATION', 'scale' => 1],
        ]],
        ['key' => 'brent_monthly', 'label' => 'سعر خام برنت (شهري)', 'frequency' => 'M', 'mode' => 'relative', 'tolerance' => 0.02, 'members' => [
            ['indicator' => 'imf.pcps.poilbre', 'scale' => 1],
            ['indicator' => 'wbpink.crude_brent', 'scale' => 1],
            ['indicator' => 'eia.brent_m', 'scale' => 1],
        ]],
        ['key' => 'wti_monthly', 'label' => 'سعر خام غرب تكساس (شهري)', 'frequency' => 'M', 'mode' => 'relative', 'tolerance' => 0.02, 'members' => [
            ['indicator' => 'imf.pcps.poilwti', 'scale' => 1],
            ['indicator' => 'wbpink.crude_wti', 'scale' => 1],
            ['indicator' => 'eia.wti_m', 'scale' => 1],
        ]],
        ['key' => 'dubai_monthly', 'label' => 'سعر خام دبي (شهري)', 'frequency' => 'M', 'mode' => 'relative', 'tolerance' => 0.02, 'members' => [
            ['indicator' => 'imf.pcps.poildub', 'scale' => 1],
            ['indicator' => 'wbpink.crude_dubai', 'scale' => 1],
        ]],
        ['key' => 'henry_hub_monthly', 'label' => 'غاز هنري هب (شهري)', 'frequency' => 'M', 'mode' => 'relative', 'tolerance' => 0.03, 'members' => [
            ['indicator' => 'imf.pcps.pngasus', 'scale' => 1],
            ['indicator' => 'wbpink.ng_us', 'scale' => 1],
            ['indicator' => 'eia.henryhub_m', 'scale' => 1],
        ]],
        ['key' => 'lng_japan_monthly', 'label' => 'الغاز المسال في آسيا (شهري)', 'frequency' => 'M', 'mode' => 'relative', 'tolerance' => 0.03, 'members' => [
            ['indicator' => 'imf.pcps.pngasjp', 'scale' => 1],
            ['indicator' => 'wbpink.lng_japan', 'scale' => 1],
        ]],
        ['key' => 'gas_europe_monthly', 'label' => 'الغاز الأوروبي (شهري)', 'frequency' => 'M', 'mode' => 'relative', 'tolerance' => 0.03, 'members' => [
            ['indicator' => 'imf.pcps.pngaseu', 'scale' => 1],
            ['indicator' => 'wbpink.ng_europe', 'scale' => 1],
        ]],
    ],
];
