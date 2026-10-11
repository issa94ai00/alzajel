<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * يُوصل صلاحيتي الحذف الجديدتين إلى دور المدير.
 *
 * دور admin لا يملك صلاحياته ضمنياً — لا وجود لـ Gate::before في التطبيق —
 * بل تُسنَد إليه صراحةً في RoleSeeder. و`deploy.sh` ينفّذ الهجرات وحدها دون
 * seeders، فلو تُركت المزامنة له لوصلت الميزة إلى الإنتاج وزرّ الحذف محجوب
 * عن الجميع. لذلك تُسند هنا.
 */
return new class extends Migration
{
    /**
     * أسماء حرفية لا ثوابت: الهجرة سِجلّ لِما جرى على القاعدة، فيجب أن تبقى
     * قابلة للتنفيذ حتى لو تغيّر كتالوج الصلاحيات في الكود أو اختفى منه اسم.
     *
     * @var list<string>
     */
    private const NEW_PERMISSIONS = [
        'transactions.delete',
        'shifts.delete',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::NEW_PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // التثبيت الجديد لم يُنشئ أدواره بعد؛ RoleSeeder سيتكفّل بها لاحقاً.
        Role::where('name', 'admin')->where('guard_name', 'web')->first()
            ?->givePermissionTo(self::NEW_PERMISSIONS);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::whereIn('name', self::NEW_PERMISSIONS)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
