<?php

/**
 * إشارات السوق: قواعد تُقيَّم على أحدث بيانات معتمدة، وكل قاعدة تنطلق مرة واحدة لكل فترة.
 * type: change_pct (تغيّر نسبي عن القراءة السابقة) | change_abs (تغيّر مطلق) | yoy_pct (عن الفترة نفسها قبل سنة، للشهري)
 *       | above / below (مستوى يتجاوز عتبة). direction للتغيّر: up | down | any.
 * tone: critical | warning | good. max_age_days: أقدم عمر لآخر قراءة تُعدّ "حديثة" (وإلا لا إشارة).
 * القالب يقبل {value} {previous} {change} {period}.
 */
return [
    'rules' => [
        ['id' => 'brent_move', 'indicator' => 'imf.pcps.poilbre', 'entity' => 'WLD', 'type' => 'change_pct', 'direction' => 'any', 'threshold' => 10.0, 'tone' => 'warning', 'max_age_days' => 75,
            'title' => 'تحرّك حاد في سعر برنت', 'message' => 'تغيّر متوسط سعر برنت {change}% في {period} (من {previous} إلى {value} دولارًا للبرميل). النفط محرك رئيسي لإيرادات الدولة.'],
        ['id' => 'dubai_low', 'indicator' => 'imf.pcps.poildub', 'entity' => 'WLD', 'type' => 'below', 'threshold' => 60.0, 'tone' => 'critical', 'max_age_days' => 75,
            'title' => 'خام دبي دون 60 دولارًا', 'message' => 'بلغ متوسط خام دبي {value} دولارًا في {period}، وهو مستوى يضغط على الميزانية والحساب الجاري.'],
        ['id' => 'dubai_high', 'indicator' => 'imf.pcps.poildub', 'entity' => 'WLD', 'type' => 'above', 'threshold' => 100.0, 'tone' => 'good', 'max_age_days' => 75,
            'title' => 'خام دبي فوق 100 دولار', 'message' => 'بلغ متوسط خام دبي {value} دولارًا في {period}: فرصة لتعزيز الفوائض وخفض الدين، مع مخاطر التقلب.'],
        ['id' => 'lng_move', 'indicator' => 'imf.pcps.pngasjp', 'entity' => 'WLD', 'type' => 'change_pct', 'direction' => 'any', 'threshold' => 15.0, 'tone' => 'warning', 'max_age_days' => 75,
            'title' => 'تحرّك حاد في سعر الغاز المسال', 'message' => 'تغيّر سعر الغاز المسال في آسيا {change}% في {period} (إلى {value} دولارًا لكل مليون وحدة حرارية).'],
        ['id' => 'food_yoy', 'indicator' => 'imf.pcps.pwheamt', 'entity' => 'WLD', 'type' => 'yoy_pct', 'direction' => 'up', 'threshold' => 20.0, 'tone' => 'warning', 'max_age_days' => 75,
            'title' => 'ارتفاع أسعار القمح', 'message' => 'ارتفع سعر القمح {change}% عن العام الماضي ({value} دولارًا للطن في {period}): ضغط تضخمي مستورد.'],
        ['id' => 'oman_inflation', 'indicator' => 'ncsi.CPI_INFLATION', 'entity' => 'OMN', 'type' => 'above', 'threshold' => 3.0, 'tone' => 'warning', 'max_age_days' => 75,
            'title' => 'التضخم في عُمان فوق 3%', 'message' => 'بلغ التضخم السنوي {value}% في {period}.'],
        ['id' => 'oman_oil_output', 'indicator' => 'ncsi.OIL_PRODUCTION', 'entity' => 'OMN', 'type' => 'change_pct', 'direction' => 'down', 'threshold' => 5.0, 'tone' => 'warning', 'max_age_days' => 75,
            'title' => 'تراجع إنتاج النفط', 'message' => 'انخفض متوسط إنتاج النفط الخام اليومي {change}% في {period} (إلى {value} ألف برميل/يوم).'],
        ['id' => 'ust10_jump', 'indicator' => 'us.yield_10y', 'entity' => 'USA', 'type' => 'change_abs', 'direction' => 'any', 'threshold' => 0.15, 'tone' => 'warning', 'max_age_days' => 7,
            'title' => 'قفزة في عائد الخزانة الأمريكية', 'message' => 'تغيّر عائد سندات الخزانة 10 سنوات {change} نقطة في {period} (إلى {value}%): ينتقل إلى كلفة التمويل بالريال المربوط بالدولار.'],
    ],
];
