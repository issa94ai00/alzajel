<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * يُوصل صلاحية الحركات النقدية إلى دور المدير.
 *
 * كما في هجرة صلاحيات الحذف: دور admin تُسنَد إليه صلاحياته صراحةً في
 * RoleSeeder، و`deploy.sh` ينفّذ الهجرات وحدها دون seeders — فلولا هذه
 * الهجرة لوصلت الميزة إلى الإنتاج وزرّها محجوب عن الجميع.
 *
 * الاسم حرفيّ لا ثابت: الهجرة سِجلّ لِما جرى على القاعدة، فتبقى قابلة
 * للتنفيذ حتى لو تغيّر كتالوج الصلاحيات في الكود.
 */
return new class extends Migration
{
    private const NEW_PERMISSION = 'shifts.deposit';

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::findOrCreate(self::NEW_PERMISSION, 'web');

        // التثبيت الجديد لم يُنشئ أدواره بعد؛ RoleSeeder سيتكفّل بها لاحقاً.
        Role::where('name', 'admin')->where('guard_name', 'web')->first()
            ?->givePermissionTo(self::NEW_PERMISSION);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::where('name', self::NEW_PERMISSION)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
