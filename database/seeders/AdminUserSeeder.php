<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@alzajel.test'],
            [
                'name' => 'مدير النظام',
                'password' => 'password',
                'email_verified_at' => now(),
            ]
        );

        $admin->assignRole('admin');

        $this->command->warn('تم إنشاء مستخدم المدير: admin@alzajel.test / password — يرجى تغيير كلمة المرور قبل الاستخدام الفعلي.');
    }
}
