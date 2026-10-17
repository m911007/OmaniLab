<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** طلب تحديث رسم يضعه مستخدم لينفّذه وكيل الذكاء الاصطناعي. */
class ChartRequest extends Model
{
    public const STATUSES = ['pending', 'in_progress', 'done', 'rejected'];

    protected $table = 'app.chart_requests';

    /** @var list<string> */
    protected $fillable = ['user_id', 'chart_key', 'chart_title', 'page_path', 'message', 'status', 'agent_response', 'handled_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
