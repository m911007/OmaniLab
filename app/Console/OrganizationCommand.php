<?php

namespace App\Console;

use App\Models\User;
use Illuminate\Console\Attributes\AsCommand;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[AsCommand(name: 'orgs:manage', description: 'إنشاء جهة (حكومية أو شركة) وإضافة أعضاء بأدوار: purchaser موظف مشتريات، analyst محلل، org_admin مدير الجهة')]
final class OrganizationCommand extends Command
{
    protected $signature = 'orgs:manage {action : create|add-member|list} {--name= : اسم الجهة بالعربية} {--kind=government : government|company} {--country=OMN} {--org= : رقم الجهة} {--email= : بريد المستخدم} {--role=purchaser : purchaser|analyst|org_admin}';

    public function handle(): int
    {
        return match ($this->argument('action')) {
            'create' => $this->create(),
            'add-member' => $this->addMember(),
            default => $this->listAll(),
        };
    }

    private function create(): int
    {
        $name = trim((string) $this->option('name'));
        if ($name === '' || ! in_array($this->option('kind'), ['government', 'company'], true)) {
            $this->error('الاسم مطلوب والنوع government أو company.');

            return self::FAILURE;
        }
        $id = DB::table('app.organizations')->insertGetId(['name_ar' => $name, 'kind' => $this->option('kind'), 'country' => strtoupper((string) $this->option('country')), 'created_at' => now(), 'updated_at' => now()]);
        $this->info("أُنشئت الجهة #{$id}: {$name}");

        return self::SUCCESS;
    }

    private function addMember(): int
    {
        $user = User::where('email', Str::lower((string) $this->option('email')))->first();
        $org = DB::table('app.organizations')->where('id', (int) $this->option('org'))->first();
        $role = (string) $this->option('role');
        if (! $user || ! $org || ! in_array($role, ['purchaser', 'analyst', 'org_admin'], true)) {
            $this->error('تحقق من البريد ورقم الجهة والدور.');

            return self::FAILURE;
        }
        DB::table('app.organization_members')->updateOrInsert(['organization_id' => $org->id, 'user_id' => $user->id], ['role' => $role, 'created_at' => now()]);
        $this->info("{$user->email} ← {$org->name_ar} بدور {$role}");

        return self::SUCCESS;
    }

    private function listAll(): int
    {
        foreach (DB::table('app.organizations')->orderBy('id')->get() as $o) {
            $n = DB::table('app.organization_members')->where('organization_id', $o->id)->count();
            $this->line("#{$o->id} {$o->name_ar} ({$o->kind}, {$o->country}) — {$n} أعضاء");
        }

        return self::SUCCESS;
    }
}
