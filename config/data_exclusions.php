<?php

/**
 * نقاط بيانات خاطئة في المصدر نفسه تُستبعد قبل النشر (لا تُستبدل بقيم مختلقة). لكل نقطة سبب موثّق يظهر في سجل التصحيحات.
 * تُطبَّق مركزيًا في IngestRunner على كل الموصّلات؛ وإن صحّح المصدر قيمته يكفي حذف السطر هنا.
 *
 * reviewed: نقاط رُشّحت انقطاعًا وتبيّن بالمراجعة أنها صحيحة (تُوسَم «لا خطأ» ولا تُستبعد).
 */
return [
    'exclude' => [
        ['indicator' => 'adp.labour.employment-by-occupation-percent-both-sexes-non-nationals-skilled-agri-bbae90', 'entity' => 'OMN', 'period' => '2022',
            'reason' => 'نصيب الوافدين من الجنسين 0.02% بينما الذكور الوافدون وحدهم 5.6%: المجموع لا يقل عن مكوّناته بهذا القدر؛ خطأ في خلية المصدر (بوابة التنمية العربية).'],
        ['indicator' => 'adp.labour.employment-by-occupation-percent-both-sexes-non-nationals-skilled-agri-bbae90', 'entity' => 'OMN', 'period' => '2019',
            'reason' => 'نصيب الوافدين من الجنسين 29.06% بينما الذكور الوافدون 5.995% والإناث قرب الصفر: يناقض مكوّناته؛ خطأ في خلية المصدر.'],
        ['indicator' => 'adp.labour.employment-by-occupation-percent-female-non-nationals-skilled-agricult-69a4c7', 'entity' => 'OMN', 'period' => '2022',
            'reason' => 'القيمة 6.37% تطابق حرفيًا قيمة «الإناث من كل الجنسيات» للسنة نفسها بعد سنوات عند 0.01% تقريبًا: يُرجَّح نسخ خلية في المصدر.'],
    ],
    'reviewed' => [
        ['indicator' => 'ncsi.CPIG_EDU_INFL', 'entity' => 'OMN', 'periods' => ['2022-10', '2022-11'],
            'reason' => 'ليس خطأ: رسوم التعليم تتغير مرة في السنة (أكتوبر)، فيثبت تضخمها السنوي 12 شهرًا (7.79% حتى سبتمبر 2022) ثم يهبط قرب الصفر حين لا تتغير الرسوم.'],
    ],
];
