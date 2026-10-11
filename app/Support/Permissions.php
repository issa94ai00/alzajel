<?php

namespace App\Support;

/**
 * الكتالوج المركزي لصلاحيات النظام.
 *
 * كل صلاحية تُعرَّف هنا مرة واحدة، ومنها تُبنى: جداول spatie عبر PermissionSeeder،
 * ومصفوفة الصلاحيات في واجهة إدارة الأدوار، والتحقق داخل السياسات والمسارات.
 */
class Permissions
{
    public const TRANSACTIONS_VIEW = 'transactions.view';

    public const TRANSACTIONS_CREATE = 'transactions.create';

    public const TRANSACTIONS_VIEW_ANY_USER = 'transactions.view_any_user';

    public const TRANSACTIONS_DELETE = 'transactions.delete';

    public const SHIFTS_VIEW = 'shifts.view';

    public const SHIFTS_VIEW_ANY = 'shifts.view_any';

    public const SHIFTS_OPEN = 'shifts.open';

    public const SHIFTS_CLOSE = 'shifts.close';

    public const SHIFTS_CLOSE_ANY = 'shifts.close_any';

    public const SHIFTS_DELETE = 'shifts.delete';

    public const SHIFTS_DEPOSIT = 'shifts.deposit';

    public const RATES_VIEW = 'rates.view';

    public const RATES_UPDATE = 'rates.update';

    public const CURRENCIES_MANAGE = 'currencies.manage';

    public const REPORTS_VIEW = 'reports.view';

    public const REPORTS_PROFIT_LOSS = 'reports.profit_loss';

    public const REPORTS_EXPORT = 'reports.export';

    public const USERS_VIEW = 'users.view';

    public const USERS_CREATE = 'users.create';

    public const USERS_UPDATE = 'users.update';

    public const USERS_DELETE = 'users.delete';

    public const USERS_MANAGE_ROLES = 'users.manage_roles';

    public const USERS_RESET_PASSWORD = 'users.reset_password';

    public const USERS_MANAGE_SESSIONS = 'users.manage_sessions';

    public const ROLES_MANAGE = 'roles.manage';

    public const ACTIVITY_VIEW = 'activity.view';

    public const SETTINGS_MANAGE = 'settings.manage';

    /**
     * الدور الذي يملك كل شيء ضمنياً (عبر Gate::before) ولا يمكن حذفه أو تعديل صلاحياته.
     */
    public const SUPER_ROLE = 'admin';

    /**
     * الأدوار المدمجة التي لا يجوز حذفها أو إعادة تسميتها.
     *
     * @var list<string>
     */
    public const SYSTEM_ROLES = ['admin', 'cashier'];

    /**
     * الصلاحيات مجمّعة حسب القسم مع التسميات العربية المستخدمة في الواجهة.
     *
     * @return array<string, array{label: string, permissions: array<string, string>}>
     */
    public static function groups(): array
    {
        return [
            'transactions' => [
                'label' => 'عمليات الصرافة',
                'permissions' => [
                    self::TRANSACTIONS_VIEW => 'عرض سجل العمليات',
                    self::TRANSACTIONS_CREATE => 'تسجيل عملية صرافة',
                    self::TRANSACTIONS_VIEW_ANY_USER => 'عرض عمليات بقية المستخدمين',
                    self::TRANSACTIONS_DELETE => 'حذف واستعادة العمليات',
                ],
            ],
            'shifts' => [
                'label' => 'الورديات',
                'permissions' => [
                    self::SHIFTS_VIEW => 'عرض الورديات',
                    self::SHIFTS_VIEW_ANY => 'عرض ورديات بقية المستخدمين',
                    self::SHIFTS_OPEN => 'فتح وردية',
                    self::SHIFTS_CLOSE => 'إغلاق ورديته الخاصة',
                    self::SHIFTS_CLOSE_ANY => 'إغلاق وردية أي مستخدم',
                    self::SHIFTS_DELETE => 'حذف واستعادة الورديات',
                    self::SHIFTS_DEPOSIT => 'إيداع وسحب نقدي من الوردية',
                ],
            ],
            'rates' => [
                'label' => 'أسعار الصرف والعملات',
                'permissions' => [
                    self::RATES_VIEW => 'عرض صفحة أسعار الصرف',
                    self::RATES_UPDATE => 'تعديل أسعار الصرف',
                    self::CURRENCIES_MANAGE => 'إدارة العملات',
                ],
            ],
            'reports' => [
                'label' => 'التقارير',
                'permissions' => [
                    self::REPORTS_VIEW => 'عرض التقارير',
                    self::REPORTS_PROFIT_LOSS => 'عرض تقارير الأرباح والخسائر',
                    self::REPORTS_EXPORT => 'تصدير التقارير',
                ],
            ],
            'users' => [
                'label' => 'المستخدمون',
                'permissions' => [
                    self::USERS_VIEW => 'عرض المستخدمين',
                    self::USERS_CREATE => 'إضافة مستخدم',
                    self::USERS_UPDATE => 'تعديل مستخدم',
                    self::USERS_DELETE => 'حذف مستخدم',
                    self::USERS_MANAGE_ROLES => 'إسناد الأدوار والصلاحيات',
                    self::USERS_RESET_PASSWORD => 'إعادة تعيين كلمات المرور',
                    self::USERS_MANAGE_SESSIONS => 'إدارة الجلسات والأجهزة',
                ],
            ],
            'system' => [
                'label' => 'النظام',
                'permissions' => [
                    self::ROLES_MANAGE => 'إدارة الأدوار',
                    self::ACTIVITY_VIEW => 'عرض سجل النشاط',
                    self::SETTINGS_MANAGE => 'إدارة الإعدادات',
                ],
            ],
        ];
    }

    /**
     * كل أسماء الصلاحيات كقائمة مسطّحة.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        $names = [];

        foreach (self::groups() as $group) {
            foreach (array_keys($group['permissions']) as $name) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * التسمية العربية لصلاحية واحدة (أو اسمها الخام إن لم تكن معرّفة).
     */
    public static function label(string $permission): string
    {
        foreach (self::groups() as $group) {
            if (isset($group['permissions'][$permission])) {
                return $group['permissions'][$permission];
            }
        }

        return $permission;
    }

    /**
     * الصلاحيات الافتراضية لكل دور مدمج.
     *
     * @return array<string, list<string>>
     */
    public static function defaultsByRole(): array
    {
        return [
            'admin' => self::all(),
            'cashier' => [
                self::TRANSACTIONS_VIEW,
                self::TRANSACTIONS_CREATE,
                self::SHIFTS_VIEW,
                self::SHIFTS_OPEN,
                // إغلاق ورديته الخاصة فقط — SHIFTS_CLOSE_ANY تبقى للمشرف.
                // بدونها يفتح الصرّاف وردية ولا يستطيع إغلاقها ولا فتح غيرها.
                self::SHIFTS_CLOSE,
                // اطّلاع فقط على أسعار الصرف؛ التعديل يبقى بـ RATES_UPDATE.
                self::RATES_VIEW,
                self::REPORTS_VIEW,
                self::REPORTS_EXPORT,
            ],
        ];
    }
}
