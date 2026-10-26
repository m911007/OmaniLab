<?php

/**
 * بيانات القراءة لكل مؤشر: ما يلزم لتحويل الرقم إلى قراءة مفهومة (حكم + جملة + مراجع).
 *
 * polarity: higher = الأعلى أفضل، lower = الأدنى أفضل، target:X = الأقرب إلى X أفضل. بلا قطبية لا يصدر حكم (مثل عدد السكان).
 *   إن لم تُذكر هنا تؤخذ من config/scorecard.php.
 * forecast: مؤشرات صندوق النقد التي تحوي توقعات؛ تُقرأ آخر سنة فعلية فقط.
 * scale/unit/digits: تحويل العرض (مثل الدولار إلى الريال العُماني) ووحدته وخاناته.
 * peer_judge: false للمؤشرات التي يُعرض فيها ترتيب الخليج دون أن يدخل في الحكم (حجم الاقتصاد ليس «جيدًا» أو «سيئًا» مقارنةً بالجيران).
 * reference: مرجع للمقارنة يُعرض مع مصدره. لا يُضاف هدف رسمي إلا بمصدر موثّق.
 */
return [
    'entity' => 'OMN',
    'peers' => ['SAU', 'ARE', 'QAT', 'KWT', 'BHR'],
    'history_years' => 10,

    // مؤشرات «نبض الاقتصاد» في الموجز التنفيذي بترتيب العرض
    'pulse' => [
        'imf.NGDP_RPCH', 'imf.NGDPD', 'ncsi.CPI_INFLATION', 'ncsi.OIL_PRICE',
        'imf.GGXCNL_NGDP', 'imf.GGXWDG_NGDP', 'imf.BCA_NGDPD', 'ilo.unemployment',
        'ncsi.MON_CREDIT_PRIVATE', 'ncsi.MON_RESERVES_FX', 'ncsi.MON_RATE_LENDING_RO', 'manafeth.EXPORTS',
        'imf.NGDPDPC', 'ncsi.POP_TOTAL',
    ],

    'indicators' => [
        'imf.NGDP_RPCH' => ['label' => 'نمو الناتج الحقيقي', 'polarity' => 'higher', 'forecast' => true, 'digits' => 1],
        'imf.NGDPD' => ['label' => 'الناتج المحلي الإجمالي', 'polarity' => 'higher', 'forecast' => true, 'scale' => 0.3845, 'unit' => 'مليار ر.ع', 'digits' => 1, 'peer_judge' => false],
        'imf.NGDPDPC' => ['label' => 'نصيب الفرد من الناتج', 'polarity' => 'higher', 'forecast' => true, 'scale' => 0.3845, 'unit' => 'ر.ع', 'digits' => 0],
        'ncsi.CPI_INFLATION' => [
            'label' => 'التضخم السنوي', 'polarity' => 'target:2', 'digits' => 1,
            'reference' => ['value' => 2.0, 'label' => 'مرجع 2%', 'source' => 'المستوى الشائع لاستقرار الأسعار لدى البنوك المركزية (ليس هدفًا رسميًا لعُمان)'],
        ],
        'ncsi.OIL_PRICE' => ['label' => 'سعر النفط العُماني', 'polarity' => 'higher', 'digits' => 1],
        'imf.GGXCNL_NGDP' => ['label' => 'رصيد الميزانية', 'polarity' => 'higher', 'forecast' => true, 'unit' => '% من الناتج', 'digits' => 1],
        'imf.GGXWDG_NGDP' => ['label' => 'الدين الحكومي', 'polarity' => 'lower', 'forecast' => true, 'unit' => '% من الناتج', 'digits' => 1],
        'imf.BCA_NGDPD' => ['label' => 'رصيد الحساب الجاري', 'polarity' => 'higher', 'forecast' => true, 'unit' => '% من الناتج', 'digits' => 1],
        'ilo.unemployment' => ['label' => 'معدل البطالة', 'polarity' => 'lower', 'digits' => 1],
        'ncsi.MON_CREDIT_PRIVATE' => ['label' => 'ائتمان القطاع الخاص', 'polarity' => 'higher', 'scale' => 0.001, 'unit' => 'مليار ر.ع', 'digits' => 2],
        'manafeth.EXPORTS' => ['label' => 'الصادرات السلعية', 'polarity' => 'higher', 'scale' => 1e-9, 'unit' => 'مليار ر.ع', 'digits' => 2],
        'ncsi.POP_TOTAL' => ['label' => 'عدد السكان', 'scale' => 1e-6, 'unit' => 'مليون نسمة', 'digits' => 2],
        'ncsi.MON_RESERVES_FX' => ['label' => 'احتياطيات النقد الأجنبي', 'polarity' => 'higher', 'scale' => 0.0003845, 'unit' => 'مليار ر.ع', 'digits' => 2],
        'ncsi.MON_M2' => ['label' => 'عرض النقد الواسع M2', 'scale' => 0.001, 'unit' => 'مليار ر.ع', 'digits' => 2],
        'ncsi.MON_RATE_LENDING_RO' => ['label' => 'سعر الإقراض بالريال', 'polarity' => 'lower', 'digits' => 2],
        'gccstat.UNEMPLOYMENT' => ['label' => 'معدل البطالة (المركز الخليجي)', 'polarity' => 'lower', 'digits' => 1],
    ],
];
