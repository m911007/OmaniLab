<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * مستخدم المنصة. الحسابات يُنشئها المشرف فقط (لا تسجيل ذاتي). is_admin يمنح إدارة الطلبات.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property bool $is_admin
 */
class User extends Authenticatable
{
    protected $table = 'app.users';

    /** @var list<string> */
    protected $fillable = ['name', 'email', 'password', 'is_admin'];

    /** @var list<string> */
    protected $hidden = ['password', 'remember_token'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_admin' => 'boolean', 'password' => 'hashed'];
    }

    /** @return HasMany<ChartRequest, $this> */
    public function chartRequests(): HasMany
    {
        return $this->hasMany(ChartRequest::class);
    }
}
