<?php

/**
 * بطاقة الأداء: المؤشرات التي تُقارَن بها عُمان بالعالم والخليج لاستخراج نقاط القوة والتحديات والفرص.
 * polarity: higher = الأعلى أفضل، lower = الأدنى أفضل، target:X = الأقرب إلى X أفضل (مثل التضخم المستهدف).
 * forecast: true لمؤشرات IMF التي تحوي توقعات بعد السنة الماضية، فيقتصر التقييم على السنوات الفعلية.
 */
return [
    'entity' => 'OMN',
    'peers' => ['SAU', 'ARE', 'QAT', 'KWT', 'BHR'],
    'baseline_years' => 5,
    'window_years' => 3,
    // مؤشر آخر قيمة لعُمان فيه أقدم من هذا العمر لا يدخل البطاقة (ترتيب بيانات قديمة يضلّل).
    'max_age_years' => 6,
    'themes' => [
        'macro' => ['label' => 'النمو والاستقرار الكلي', 'indicators' => [
            ['key' => 'imf.NGDP_RPCH', 'polarity' => 'higher', 'forecast' => true],
            ['key' => 'imf.PCPIPCH', 'polarity' => 'target:2', 'forecast' => true],
            ['key' => 'imf.PPPPC', 'polarity' => 'higher', 'forecast' => true],
        ]],
        'fiscal' => ['label' => 'المالية العامة', 'indicators' => [
            ['key' => 'imf.GGXCNL_NGDP', 'polarity' => 'higher', 'forecast' => true],
            ['key' => 'imf.GGXWDG_NGDP', 'polarity' => 'lower', 'forecast' => true],
            ['key' => 'wb.GC.TAX.TOTL.GD.ZS', 'polarity' => 'higher'],
            ['key' => 'imf.ffs_explicit', 'polarity' => 'lower', 'forecast' => true],
            ['key' => 'imf.ffs_implicit', 'polarity' => 'lower', 'forecast' => true],
        ]],
        'external' => ['label' => 'القطاع الخارجي والتنويع', 'indicators' => [
            ['key' => 'imf.BCA_NGDPD', 'polarity' => 'higher', 'forecast' => true],
            ['key' => 'imf.Reserves_M', 'polarity' => 'higher'],
            ['key' => 'wb.TX.VAL.FUEL.ZS.UN', 'polarity' => 'lower'],
            ['key' => 'wb.TX.VAL.MANF.ZS.UN', 'polarity' => 'higher'],
            ['key' => 'wb.TX.VAL.TECH.MF.ZS', 'polarity' => 'higher'],
            ['key' => 'oec.eci', 'polarity' => 'higher'],
            ['key' => 'wb.BX.KLT.DINV.WD.GD.ZS', 'polarity' => 'higher'],
            ['key' => 'wb.NY.GDP.PETR.RT.ZS', 'polarity' => 'lower'],
        ]],
        'labour' => ['label' => 'سوق العمل', 'indicators' => [
            ['key' => 'ilo.unemployment', 'polarity' => 'lower'],
            ['key' => 'ilo.unemployment_youth', 'polarity' => 'lower'],
            ['key' => 'ilo.neet', 'polarity' => 'lower'],
            ['key' => 'ilo.lfp_total', 'polarity' => 'higher'],
            ['key' => 'ilo.lfp_female', 'polarity' => 'higher'],
        ]],
        'human' => ['label' => 'رأس المال البشري والتعليم', 'indicators' => [
            ['key' => 'undp.hdi', 'polarity' => 'higher'],
            ['key' => 'wb.HD.HCI.OVRL', 'polarity' => 'higher'],
            ['key' => 'undp.eys', 'polarity' => 'higher'],
            ['key' => 'wb.SE.TER.ENRR', 'polarity' => 'higher'],
            ['key' => 'wb.GB.XPD.RSDV.GD.ZS', 'polarity' => 'higher'],
            ['key' => 'wb.HD.HCI.LAYS', 'polarity' => 'higher'],
            ['key' => 'owid.happiness', 'polarity' => 'higher'],
        ]],
        'health' => ['label' => 'الصحة', 'indicators' => [
            ['key' => 'who.hale', 'polarity' => 'higher'],
            ['key' => 'who.uhc_index', 'polarity' => 'higher'],
            ['key' => 'who.ncd_mortality', 'polarity' => 'lower'],
            ['key' => 'who.obesity', 'polarity' => 'lower'],
            ['key' => 'wb.SH.DYN.MORT', 'polarity' => 'lower'],
            ['key' => 'fao.cereal_import_dependency', 'polarity' => 'lower'],
            ['key' => 'fao.child_stunting', 'polarity' => 'lower'],
        ]],
        'governance' => ['label' => 'الحوكمة وبيئة الأعمال', 'indicators' => [
            ['key' => 'wb.GOV_WGI_GE.SC', 'polarity' => 'higher'],
            ['key' => 'wb.GOV_WGI_RQ.SC', 'polarity' => 'higher'],
            ['key' => 'wb.GOV_WGI_RL.SC', 'polarity' => 'higher'],
            ['key' => 'wb.GOV_WGI_CC.SC', 'polarity' => 'higher'],
            ['key' => 'owid.cpi_score', 'polarity' => 'higher'],
            ['key' => 'wb.LP.LPI.OVRL.XQ', 'polarity' => 'higher'],
        ]],
        'digital' => ['label' => 'الاقتصاد الرقمي والابتكار', 'indicators' => [
            ['key' => 'imf.AI_PI', 'polarity' => 'higher'],
            ['key' => 'imf.DI', 'polarity' => 'higher'],
            ['key' => 'wb.IT.NET.USER.ZS', 'polarity' => 'higher'],
            ['key' => 'wb.IT.NET.BBND.P2', 'polarity' => 'higher'],
            ['key' => 'wb.SP.POP.SCIE.RD.P6', 'polarity' => 'higher'],
        ]],
        'climate' => ['label' => 'الطاقة والبيئة والمناخ', 'indicators' => [
            ['key' => 'undp.co2_prod', 'polarity' => 'lower'],
            ['key' => 'wb.EG.FEC.RNEW.ZS', 'polarity' => 'higher'],
            ['key' => 'wb.EN.ATM.PM25.MC.M3', 'polarity' => 'lower'],
            ['key' => 'imf.ndgain_index', 'polarity' => 'higher'],
            ['key' => 'wb.NY.ADJ.SVNG.GN.ZS', 'polarity' => 'higher'],
            ['key' => 'wb.ER.H2O.FWST.ZS', 'polarity' => 'lower'],
            ['key' => 'owid.renewable_electricity_share', 'polarity' => 'higher'],
            ['key' => 'owid.electricity_carbon_intensity', 'polarity' => 'lower'],
        ]],
        'finance' => ['label' => 'القطاع المالي', 'indicators' => [
            ['key' => 'wb.FS.AST.PRVT.GD.ZS', 'polarity' => 'higher'],
            ['key' => 'wb.FX.OWN.TOTL.ZS', 'polarity' => 'higher'],
            ['key' => 'wb.CM.MKT.LCAP.GD.ZS', 'polarity' => 'higher'],
            ['key' => 'wb.GFDD.SI.02', 'polarity' => 'lower'],
            ['key' => 'wb.GFDD.SI.05', 'polarity' => 'higher'],
            ['key' => 'wb.GFDD.SI.01', 'polarity' => 'higher'],
        ]],
    ],
];
