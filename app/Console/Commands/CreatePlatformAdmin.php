<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreatePlatformAdmin extends Command
{
    protected $signature = 'accma:create-platform-admin
                            {--email=super@kh.ps : بريد مدير المنصة}
                            {--password=12312300 : كلمة المرور}
                            {--name=مدير المنصة : الاسم}';

    protected $description = 'إنشاء أو تحديث حساب سوبر أدمن للمنصة (لوحة /super)';

    public function handle(): int
    {
        $email = $this->option('email');
        $password = $this->option('password');
        $name = $this->option('name');

        $user = User::query()->where('email', $email)->first();
        if ($user) {
            $user->update([
                'name' => $name,
                'password' => Hash::make($password),
                'is_platform_admin' => true,
                'is_super_admin' => false,
                'tenant_id' => null,
                'is_active' => true,
            ]);
            $this->info("تم تحديث مدير المنصة: {$email}");
        } else {
            User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'role_id' => Role::query()->where('slug', 'admin')->value('id'),
                'is_platform_admin' => true,
                'is_super_admin' => false,
                'tenant_id' => null,
                'is_active' => true,
            ]);
            $this->info("تم إنشاء مدير المنصة: {$email}");
        }

        $this->line('الدخول: /super/login');

        return self::SUCCESS;
    }
}
