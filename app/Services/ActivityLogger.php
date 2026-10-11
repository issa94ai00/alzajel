<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * سجل النشاط: من فعل ماذا، على أي كيان، ومن أي جهاز.
 */
class ActivityLogger
{
    public const AUTH_LOGIN = 'auth.login';

    public const AUTH_LOGOUT = 'auth.logout';

    public const AUTH_FAILED = 'auth.failed';

    public const AUTH_BLOCKED = 'auth.blocked';

    public const USER_CREATED = 'user.created';

    public const USER_UPDATED = 'user.updated';

    public const USER_DELETED = 'user.deleted';

    public const USER_RESTORED = 'user.restored';

    public const USER_STATUS_CHANGED = 'user.status_changed';

    public const USER_PASSWORD_RESET = 'user.password_reset';

    public const USER_ROLES_CHANGED = 'user.roles_changed';

    public const USER_PERMISSIONS_CHANGED = 'user.permissions_changed';

    public const USER_SESSIONS_REVOKED = 'user.sessions_revoked';

    public const USER_TOKENS_REVOKED = 'user.tokens_revoked';

    public const ROLE_CREATED = 'role.created';

    public const ROLE_UPDATED = 'role.updated';

    public const ROLE_DELETED = 'role.deleted';

    public const SHIFT_OPENED = 'shift.opened';

    public const SHIFT_CLOSED = 'shift.closed';

    public const SHIFT_FORCE_CLOSED = 'shift.force_closed';

    public const SHIFT_DELETED = 'shift.deleted';

    public const SHIFT_RESTORED = 'shift.restored';

    public const TRANSACTION_DELETED = 'transaction.deleted';

    public const TRANSACTION_RESTORED = 'transaction.restored';

    public const SHIFT_DEPOSIT = 'shift.deposit';

    public const SHIFT_WITHDRAWAL = 'shift.withdrawal';

    public const SHIFT_MOVEMENT_DELETED = 'shift.movement_deleted';

    public const SHIFT_MOVEMENT_RESTORED = 'shift.movement_restored';

    /**
     * التسميات العربية للأحداث، تُستخدم في فلاتر واجهة سجل النشاط.
     *
     * @var array<string, string>
     */
    public const EVENT_LABELS = [
        self::AUTH_LOGIN => 'تسجيل دخول',
        self::AUTH_LOGOUT => 'تسجيل خروج',
        self::AUTH_FAILED => 'محاولة دخول فاشلة',
        self::AUTH_BLOCKED => 'دخول مرفوض (حساب موقوف)',
        self::USER_CREATED => 'إنشاء مستخدم',
        self::USER_UPDATED => 'تعديل مستخدم',
        self::USER_DELETED => 'حذف مستخدم',
        self::USER_RESTORED => 'استعادة مستخدم',
        self::USER_STATUS_CHANGED => 'تغيير حالة مستخدم',
        self::USER_PASSWORD_RESET => 'إعادة تعيين كلمة مرور',
        self::USER_ROLES_CHANGED => 'تغيير أدوار مستخدم',
        self::USER_PERMISSIONS_CHANGED => 'تغيير صلاحيات مستخدم',
        self::USER_SESSIONS_REVOKED => 'إنهاء جلسات مستخدم',
        self::USER_TOKENS_REVOKED => 'إلغاء رموز تطبيق الجوال',
        self::ROLE_CREATED => 'إنشاء دور',
        self::ROLE_UPDATED => 'تعديل دور',
        self::ROLE_DELETED => 'حذف دور',
        self::SHIFT_OPENED => 'فتح وردية',
        self::SHIFT_CLOSED => 'إغلاق وردية',
        self::SHIFT_FORCE_CLOSED => 'إغلاق وردية بواسطة مشرف',
        self::SHIFT_DELETED => 'حذف وردية',
        self::SHIFT_RESTORED => 'استعادة وردية',
        self::TRANSACTION_DELETED => 'حذف عملية صرافة',
        self::TRANSACTION_RESTORED => 'استعادة عملية صرافة',
        self::SHIFT_DEPOSIT => 'إيداع نقدي في وردية',
        self::SHIFT_WITHDRAWAL => 'سحب نقدي من وردية',
        self::SHIFT_MOVEMENT_DELETED => 'حذف حركة نقدية',
        self::SHIFT_MOVEMENT_RESTORED => 'استعادة حركة نقدية',
    ];

    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(
        string $event,
        string $description,
        ?Model $subject = null,
        array $properties = [],
        ?User $causer = null,
    ): ActivityLog {
        $causer ??= Auth::user();

        return ActivityLog::create([
            'causer_id' => $causer?->getKey(),
            'causer_name' => $causer?->name,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'event' => $event,
            'description' => $description,
            'properties' => $properties ?: null,
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 512) ?: null,
        ]);
    }

    /**
     * يسجّل الحقول التي تغيّرت فعلاً فقط، مع إخفاء أي قيم حساسة.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{from: mixed, to: mixed}>
     */
    public function diff(array $before, array $after): array
    {
        $changes = [];

        foreach ($after as $key => $newValue) {
            $oldValue = $before[$key] ?? null;

            if ($oldValue == $newValue) {
                continue;
            }

            if (in_array($key, ['password', 'password_confirmation', 'remember_token'], true)) {
                $changes[$key] = ['from' => '***', 'to' => '***'];

                continue;
            }

            $changes[$key] = ['from' => $oldValue, 'to' => $newValue];
        }

        return $changes;
    }

    public static function label(string $event): string
    {
        return self::EVENT_LABELS[$event] ?? $event;
    }
}
