<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminUser extends Command
{
    protected $signature = 'user:create-admin 
                            {--email=admin@example.com : البريد الإلكتروني}
                            {--password=password : كلمة المرور}
                            {--name=مدير النظام : الاسم}';

    protected $description = 'إنشاء مستخدم مدير نظام';

    public function handle(): int
    {
        $email = $this->option('email');
        $password = $this->option('password');
        $name = $this->option('name');

        if (User::where('email', $email)->exists()) {
            $this->warn("المستخدم {$email} موجود مسبقاً. استخدم user:reset-password لتغيير كلمة المرور.");
            return 1;
        }

        $adminRole = Role::where('slug', 'admin')->first();
        if (!$adminRole) {
            $this->error('الدور "admin" غير موجود. قم بتشغيل: php artisan db:seed --class=RolesAndPermissionsSeeder');
            return 1;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role_id' => $adminRole->id,
            'is_super_admin' => true,
            'is_active' => true,
        ]);

        $this->info("تم إنشاء مدير النظام بنجاح.");
        $this->info("البريد: {$email}");
        $this->info("كلمة المرور: {$password}");
        return 0;
    }
}
