<?php

namespace App\Console;

use App\Models\User;
use Illuminate\Console\Attributes\AsCommand;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[AsCommand(name: 'users:create', description: 'إنشاء مستخدم (أو إعادة ضبط كلمة مروره) — يُنشئ المشرف الحسابات ولا يوجد تسجيل ذاتي')]
final class CreateUserCommand extends Command
{
    protected $signature = 'users:create {email : البريد الإلكتروني} {--name= : الاسم} {--admin : صلاحية مشرف (is_admin)} {--password= : كلمة المرور؛ تُولَّد عشوائيًا إن لم تُمرَّر}';

    public function handle(): int
    {
        $email = Str::lower((string) $this->argument('email'));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('بريد إلكتروني غير صالح.');

            return self::FAILURE;
        }
        $password = (string) ($this->option('password') ?: Str::password(16, symbols: false));
        if (strlen($password) < 10) {
            $this->error('كلمة المرور أقصر من 10 أحرف.');

            return self::FAILURE;
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->name = (string) ($this->option('name') ?: $user->name ?: Str::before($email, '@'));
        $user->password = $password;
        if ($this->option('admin')) {
            $user->is_admin = true;
        }
        $user->save();

        $this->info(($user->wasRecentlyCreated ? 'أُنشئ' : 'حُدّث')." المستخدم {$email}".($user->is_admin ? ' (مشرف)' : ''));
        if (! $this->option('password')) {
            $this->line("كلمة المرور (تظهر مرة واحدة): {$password}");
        }

        return self::SUCCESS;
    }
}
