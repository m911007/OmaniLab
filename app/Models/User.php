<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;

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

    /**
     * عضويات الجهات بأدوارها (موظف مشتريات، محلل، مدير الجهة). is_admin = مدير النظام.
     *
     * @return list<array{organization_id: int, name_ar: string, kind: string, role: string}>
     */
    public function memberships(): array
    {
        return DB::table('app.organization_members as m')->join('app.organizations as o', 'o.id', '=', 'm.organization_id')
            ->where('m.user_id', $this->id)->orderBy('o.id')->get(['o.id as organization_id', 'o.name_ar', 'o.kind', 'm.role'])->map(fn ($r) => (array) $r)->all();
    }

    /** تعديل سلاسل القيمة (بيانات مرجعية مشتركة): مدير النظام أو محلل/مدير جهة. */
    public function canEditChains(): bool
    {
        return $this->is_admin || collect($this->memberships())->contains(fn ($m) => in_array($m['role'], ['analyst', 'org_admin'], true));
    }

    /** @return HasMany<ChartRequest, $this> */
    public function chartRequests(): HasMany
    {
        return $this->hasMany(ChartRequest::class);
    }
}
