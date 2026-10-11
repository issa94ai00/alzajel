<?php

namespace Database\Seeders;

use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * يزامن كتالوج الصلاحيات مع قاعدة البيانات وينشئ الأدوار المدمجة.
     *
     * قابل لإعادة التشغيل: أي صلاحية جديدة تُضاف إلى Permissions تصل تلقائياً
     * إلى دور المدير عند إعادة تنفيذ هذا الـ seeder.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permissions::all() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (Permissions::defaultsByRole() as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');

            // المدير يُزامَن دائماً على كامل الكتالوج؛ بقية الأدوار المدمجة
            // تُمنح افتراضياتها عند الإنشاء فقط حتى لا نلغي تخصيص العميل.
            if ($roleName === Permissions::SUPER_ROLE) {
                $role->syncPermissions($permissions);

                continue;
            }

            if ($role->permissions()->doesntExist()) {
                $role->syncPermissions($permissions);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
