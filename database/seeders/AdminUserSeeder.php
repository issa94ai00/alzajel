<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@damatech.sy'],
            [
                'name' => 'مدير النظام',
                'password' => 'secret12345',
                'email_verified_at' => now(),
                'status' => User::STATUS_ACTIVE,
                'must_change_password' => true,
                'password_changed_at' => now(),
            ]
        );

        $admin->assignRole('admin');

        $this->command->warn('تم إنشاء مستخدم المدير: admin@damatech.sy / secret12345 — يرجى تغيير كلمة المرور قبل الاستخدام الفعلي.');
    }
}
