<?php

/**
 * إشارات السوق: قواعد تُقيَّم على أحدث بيانات معتمدة، وكل قاعدة تنطلق مرة واحدة لكل فترة.
 * type: change_pct (تغيّر نسبي عن القراءة السابقة) | change_abs (تغيّر مطلق) | yoy_pct (عن الفترة نفسها قبل سنة، للشهري)
 *       | above / below (مستوى يتجاوز عتبة). direction للتغيّر: up | down | any.
 * tone: critical | warning | good. max_age_days: أقدم عمر لآخر قراءة تُعدّ "حديثة" (وإلا لا إشارة).
 * القالب يقبل {value} {previous} {change} {period}.
 * why: لماذا تهم صانع القرار، watch: ماذا يراقب بعدها، link: الصفحة التي تفصّلها (الموجز التنفيذي يعرضها).
 */
return [
    'rules' => [
        ['id' => 'brent_move', 'indicator' => 'imf.pcps.poilbre', 'entity' => 'WLD', 'type' => 'change_pct', 'direction' => 'any', 'threshold' => 10.0, 'tone' => 'warning', 'max_age_days' => 75,
            'title' => 'تحرّك حاد في سعر برنت', 'message' => 'تغيّر متوسط سعر برنت {change}% في {period} (من {previous} إلى {value} دولارًا للبرميل). النفط محرك رئيسي لإيرادات الدولة.',
            'why' => 'كل تغيّر في سعر البرميل ينتقل إلى الإيرادات العامة ورصيد الميزانية خلال العام نفسه.', 'watch' => 'تقديرات الإيرادات وسعر النفط المفترض في الميزانية.', 'link' => '/impact#oil-balance-debt'],
        ['id' => 'oman_oil_low', 'indicator' => 'ncsi.OIL_PRICE', 'entity' => 'OMN', 'type' => 'below', 'threshold' => 60.0, 'tone' => 'critical', 'max_age_days' => 75,
            'title' => 'نفط عُمان دون 60 دولارًا', 'message' => 'بلغ متوسط سعر نفط عُمان {value} دولارًا في {period}، وهو مستوى يضغط على الميزانية والحساب الجاري.',
            'why' => 'الميزانية مبنية على سعر افتراضي للبرميل؛ النزول تحته يعني عجزًا أكبر واقتراضًا أو سحبًا من الاحتياطيات.', 'watch' => 'رصيد الميزانية والدين وتحرك الاحتياطيات.', 'link' => '/fiscal'],
        ['id' => 'oman_oil_high', 'indicator' => 'ncsi.OIL_PRICE', 'entity' => 'OMN', 'type' => 'above', 'threshold' => 100.0, 'tone' => 'good', 'max_age_days' => 75,
            'title' => 'نفط عُمان فوق 100 دولار', 'message' => 'بلغ متوسط سعر نفط عُمان {value} دولارًا في {period}: فرصة لتعزيز الفوائض وخفض الدين، مع مخاطر التقلب.',
            'why' => 'الفوائض فرصة لسداد الدين وبناء الاحتياطيات، لكن التوسع في الإنفاق الجاري يصعب التراجع عنه حين تهبط الأسعار.', 'watch' => 'نسبة الدين إلى الناتج والإنفاق الجاري.', 'link' => '/fiscal'],
        ['id' => 'lng_move', 'indicator' => 'imf.pcps.pngasjp', 'entity' => 'WLD', 'type' => 'change_pct', 'direction' => 'any', 'threshold' => 15.0, 'tone' => 'warning', 'max_age_days' => 75,
            'title' => 'تحرّك حاد في سعر الغاز المسال', 'message' => 'تغيّر سعر الغاز المسال في آسيا {change}% في {period} (إلى {value} دولارًا لكل مليون وحدة حرارية).',
            'why' => 'الغاز المسال مصدر مهم لصادرات عُمان وإيراداتها.', 'watch' => 'صادرات الغاز والحساب الجاري.', 'link' => '/external'],
        ['id' => 'food_yoy', 'indicator' => 'imf.pcps.pwheamt', 'entity' => 'WLD', 'type' => 'yoy_pct', 'direction' => 'up', 'threshold' => 20.0, 'tone' => 'warning', 'max_age_days' => 75,
            'title' => 'ارتفاع أسعار القمح', 'message' => 'ارتفع سعر القمح {change}% عن العام الماضي ({value} دولارًا للطن في {period}): ضغط تضخمي مستورد.',
            'why' => 'عُمان تستورد معظم غذائها؛ غلاء القمح ينتقل إلى أسعار الخبز والسلع الأساسية.', 'watch' => 'تضخم بند الغذاء والدعم.', 'link' => '/impact#food-inflation'],
        ['id' => 'oman_inflation', 'indicator' => 'ncsi.CPI_INFLATION', 'entity' => 'OMN', 'type' => 'above', 'threshold' => 3.0, 'tone' => 'warning', 'max_age_days' => 75,
            'title' => 'التضخم في عُمان فوق 3%', 'message' => 'بلغ التضخم السنوي {value}% في {period}.',
            'why' => 'التضخم فوق 3% يضغط على القوة الشرائية للأسر وعلى كلفة المشاريع.', 'watch' => 'بنود التضخم الأعلى ارتفاعًا والأجور الحقيقية.', 'link' => '/prices-living'],
        ['id' => 'oman_oil_output', 'indicator' => 'ncsi.OIL_PRODUCTION', 'entity' => 'OMN', 'type' => 'change_pct', 'direction' => 'down', 'threshold' => 5.0, 'tone' => 'warning', 'max_age_days' => 75,
            'title' => 'تراجع إنتاج النفط', 'message' => 'انخفض متوسط إنتاج النفط الخام اليومي {change}% في {period} (إلى {value} ألف برميل/يوم).',
            'why' => 'الكميات المنتجة تحرك النمو الحقيقي والإيرادات حتى مع ثبات الأسعار.', 'watch' => 'حصص أوبك+ ونمو الناتج الحقيقي.', 'link' => '/energy'],
        ['id' => 'ust10_jump', 'indicator' => 'us.yield_10y', 'entity' => 'USA', 'type' => 'change_abs', 'direction' => 'any', 'threshold' => 0.15, 'tone' => 'warning', 'max_age_days' => 7,
            'title' => 'قفزة في عائد الخزانة الأمريكية', 'message' => 'تغيّر عائد سندات الخزانة 10 سنوات {change} نقطة في {period} (إلى {value}%): ينتقل إلى كلفة التمويل بالريال المربوط بالدولار.',
            'why' => 'ربط الريال بالدولار ينقل الفائدة الأمريكية إلى كلفة الاقتراض الحكومي والخاص في عُمان.', 'watch' => 'عوائد السندات العُمانية وسعر الإقراض بالريال.', 'link' => '/impact#us-rates-credit'],
        ['id' => 'repo_move', 'indicator' => 'ncsi.MON_RATE_REPO', 'entity' => 'OMN', 'type' => 'change_abs', 'direction' => 'any', 'threshold' => 0.25, 'tone' => 'warning', 'max_age_days' => 75,
            'title' => 'تغيّر في سعر الريبو', 'message' => 'تغيّر متوسط سعر الريبو مع البنك المركزي {change} نقطة في {period} (إلى {value}%): ينتقل إلى كلفة الإقراض والودائع.',
            'why' => 'الريبو يحدد كلفة السيولة على البنوك وينتقل إلى القروض والودائع.', 'watch' => 'سعر الإقراض بالريال ونمو ائتمان القطاع الخاص.', 'link' => '/money'],
        ['id' => 'reserves_drop', 'indicator' => 'ncsi.MON_RESERVES_EX_GOLD', 'entity' => 'OMN', 'type' => 'change_pct', 'direction' => 'down', 'threshold' => 5.0, 'tone' => 'critical', 'max_age_days' => 75,
            'title' => 'انخفاض الاحتياطيات الرسمية', 'message' => 'انخفضت الاحتياطيات الرسمية دون الذهب {change}% في {period} (إلى {value} مليون دولار).',
            'why' => 'الاحتياطيات خط الدفاع عن ربط الريال بالدولار وعن الثقة في الاقتصاد.', 'watch' => 'الحساب الجاري وسعر النفط وتدفقات رأس المال.', 'link' => '/external'],
    ],
];
