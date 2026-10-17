<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** ملاحظة داخلية على رسم، يراها المسجَّلون فقط. */
class ChartNote extends Model
{
    protected $table = 'app.chart_notes';

    /** @var list<string> */
    protected $fillable = ['user_id', 'chart_key', 'body'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
