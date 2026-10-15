<?php

namespace App\Core\Api;

use Illuminate\Support\Facades\DB;

/**
 * بصمة نسخة البيانات المعروضة: تتغير عند أي ترحيل جديد، فتُبطل كاش الـ API تلقائيًا دون جدولة إبطال يدوي.
 */
final class DataVersion
{
    public static function token(): string
    {
        $row = DB::selectOne('SELECT max(last_changed_at) c, max(updated_at) u FROM ops.datasets');

        return md5(($row->c ?? '').'|'.($row->u ?? ''));
    }
}
