<?php

namespace App\Core\Freshness;

enum DatasetStatus: string
{
    case Pending = 'pending';           // لم تُجلب بعد
    case Fresh = 'fresh';               // محدّثة ضمن الدورية
    case Stale = 'stale';               // تأخرت عن موعدها
    case Quarantined = 'quarantined';   // فشل التحقق؛ تُعرض آخر نسخة سليمة
    case Failing = 'failing';           // فشل الجلب مرارًا
    case Manual = 'manual';             // تُحدَّث يدويًا (مثل opendata.gov.om حاليًا)
    case Disabled = 'disabled';

    public function labelAr(): string
    {
        return match ($this) {
            self::Pending => 'بانتظار أول جلب',
            self::Fresh => 'محدّثة',
            self::Stale => 'متأخرة',
            self::Quarantined => 'محجوزة للمراجعة',
            self::Failing => 'فشل الجلب',
            self::Manual => 'تحديث يدوي',
            self::Disabled => 'معطّلة',
        };
    }
}
