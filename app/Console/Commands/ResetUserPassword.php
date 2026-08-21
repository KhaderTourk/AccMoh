<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ResetUserPassword extends Command
{
    protected $signature = 'user:reset-password {email : البريد الإلكتروني} {password? : كلمة المرور الجديدة}';

    protected $description = 'إعادة تعيين كلمة مرور مستخدم';

    public function handle(): int
    {
        $email = $this->argument('email');
        $password = $this->argument('password') ?? $this->secret('أدخل كلمة المرور الجديدة');

        $user = User::where('email', $email)->first();
        if (!$user) {
            $this->error("المستخدم بـ البريد {$email} غير موجود.");
            return 1;
        }

        $user->update(['password' => Hash::make($password)]);
        $this->info('تم تغيير كلمة المرور بنجاح.');
        return 0;
    }
}
