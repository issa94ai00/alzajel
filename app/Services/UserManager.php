<?php

namespace App\Services;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * كل التعديلات على حسابات المستخدمين تمرّ من هنا، لتبقى قواعد العمل
 * وتسجيل النشاط في مكان واحد بدل تكرارها في كل متحكّم.
 */
class UserManager
{
    /**
     * الحقول التي نتتبّع تغيّرها في سجل النشاط.
     *
     * @var list<string>
     */
    private const TRACKED = ['name', 'email', 'phone', 'employee_no', 'status', 'notes'];

    public function __construct(
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): User
    {
        $user = new User;

        $user->fill(Arr::only($data, ['name', 'email', 'phone', 'employee_no', 'notes']));
        $user->password = $data['password'];
        $user->status = $data['status'] ?? User::STATUS_ACTIVE;
        $user->must_change_password = (bool) ($data['must_change_password'] ?? false);
        $user->password_changed_at = now();
        $user->created_by = $actor->getKey();
        $user->email_verified_at = now();
        $user->save();

        $user->syncRoles(Arr::wrap($data['roles'] ?? []));

        $this->activity->log(
            ActivityLogger::USER_CREATED,
            "إنشاء المستخدم {$user->name}",
            $user,
            [
                'email' => $user->email,
                'status' => $user->status,
                'roles' => $user->getRoleNames()->all(),
            ],
            $actor,
        );

        return $user;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data, User $actor): User
    {
        $before = $user->only(self::TRACKED);

        $user->fill(Arr::only($data, ['name', 'email', 'phone', 'employee_no', 'notes']));

        if (array_key_exists('status', $data) && $data['status'] !== null) {
            $this->applyStatus($user, $data['status']);
        }

        if (array_key_exists('must_change_password', $data)) {
            $user->must_change_password = (bool) $data['must_change_password'];
        }

        $user->save();

        $changes = $this->activity->diff($before, $user->only(self::TRACKED));

        if ($changes !== []) {
            $this->activity->log(
                ActivityLogger::USER_UPDATED,
                "تعديل بيانات المستخدم {$user->name}",
                $user,
                ['changes' => $changes],
                $actor,
            );
        }

        return $user;
    }

    public function changeStatus(User $user, string $status, ?string $reason, User $actor): User
    {
        $previous = $user->status;

        if ($previous === $status) {
            return $user;
        }

        $this->applyStatus($user, $status, $reason);
        $user->save();

        // الحساب الموقوف أو المعطّل يجب ألا يبقى له وصول قائم.
        if ($status !== User::STATUS_ACTIVE) {
            $this->revokeSessions($user, $actor, log: false);
            $this->revokeTokens($user, $actor, log: false);
        }

        $this->activity->log(
            ActivityLogger::USER_STATUS_CHANGED,
            "تغيير حالة المستخدم {$user->name} من «".(User::STATUS_LABELS[$previous] ?? $previous).'» إلى «'.$user->statusLabel().'»',
            $user,
            ['from' => $previous, 'to' => $status, 'reason' => $reason],
            $actor,
        );

        return $user;
    }

    public function resetPassword(User $user, string $password, bool $mustChange, User $actor): User
    {
        $user->password = $password;
        $user->password_changed_at = now();
        $user->must_change_password = $mustChange;
        $user->setRememberToken(Str::random(60));
        $user->save();

        // كلمة مرور جديدة تعني إبطال كل ما هو قائم من وصول.
        $this->revokeSessions($user, $actor, log: false);
        $this->revokeTokens($user, $actor, log: false);

        $this->activity->log(
            ActivityLogger::USER_PASSWORD_RESET,
            "إعادة تعيين كلمة مرور المستخدم {$user->name}",
            $user,
            ['must_change_password' => $mustChange],
            $actor,
        );

        return $user;
    }

    /**
     * @param  list<string>  $roles
     * @param  list<string>|null  $directPermissions  صلاحيات إضافية خاصة بهذا المستخدم فوق أدواره
     */
    public function syncAccess(User $user, array $roles, ?array $directPermissions, User $actor): User
    {
        $beforeRoles = $user->getRoleNames()->sort()->values()->all();
        $beforePermissions = $user->getDirectPermissions()->pluck('name')->sort()->values()->all();

        $user->syncRoles($roles);

        if ($directPermissions !== null) {
            $user->syncPermissions(array_values(array_intersect($directPermissions, Permissions::all())));
        }

        $user->unsetRelation('roles')->unsetRelation('permissions');
        $user->forgetCachedPermissions();

        $afterRoles = $user->getRoleNames()->sort()->values()->all();
        $afterPermissions = $user->getDirectPermissions()->pluck('name')->sort()->values()->all();

        if ($beforeRoles !== $afterRoles) {
            $this->activity->log(
                ActivityLogger::USER_ROLES_CHANGED,
                "تغيير أدوار المستخدم {$user->name}",
                $user,
                ['from' => $beforeRoles, 'to' => $afterRoles],
                $actor,
            );
        }

        if ($beforePermissions !== $afterPermissions) {
            $this->activity->log(
                ActivityLogger::USER_PERMISSIONS_CHANGED,
                "تغيير الصلاحيات المباشرة للمستخدم {$user->name}",
                $user,
                ['from' => $beforePermissions, 'to' => $afterPermissions],
                $actor,
            );
        }

        return $user;
    }

    public function delete(User $user, User $actor): void
    {
        $this->revokeSessions($user, $actor, log: false);
        $this->revokeTokens($user, $actor, log: false);

        // حذف ناعم: العمليات والورديات التاريخية تحتفظ باسم من نفّذها.
        $user->delete();

        $this->activity->log(
            ActivityLogger::USER_DELETED,
            "حذف المستخدم {$user->name}",
            $user,
            ['email' => $user->email],
            $actor,
        );
    }

    public function restore(User $user, User $actor): User
    {
        $user->restore();

        $this->activity->log(
            ActivityLogger::USER_RESTORED,
            "استعادة المستخدم {$user->name}",
            $user,
            ['email' => $user->email],
            $actor,
        );

        return $user;
    }

    /**
     * جلسات المتصفح النشطة لهذا المستخدم (سائق الجلسات هو قاعدة البيانات).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function sessions(User $user, ?string $currentSessionId = null): Collection
    {
        if (Config::get('session.driver') !== 'database') {
            return collect();
        }

        return collect(
            DB::table(Config::get('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->orderByDesc('last_activity')
                ->get()
        )->map(fn ($session) => [
            'id' => $session->id,
            'ip_address' => $session->ip_address,
            'device' => $this->deviceLabel($session->user_agent),
            'user_agent' => $session->user_agent,
            'last_active_at' => $session->last_activity
                ? now()->setTimestamp($session->last_activity)->toIso8601String()
                : null,
            'is_current' => $currentSessionId !== null && $session->id === $currentSessionId,
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function tokens(User $user): Collection
    {
        return $user->tokens()
            ->orderByDesc('last_used_at')
            ->get()
            ->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'created_at' => $token->created_at?->toIso8601String(),
            ]);
    }

    public function revokeSessions(User $user, User $actor, ?string $exceptSessionId = null, bool $log = true): int
    {
        if (Config::get('session.driver') !== 'database') {
            return 0;
        }

        $deleted = DB::table(Config::get('session.table', 'sessions'))
            ->where('user_id', $user->getKey())
            ->when($exceptSessionId, fn ($q) => $q->where('id', '!=', $exceptSessionId))
            ->delete();

        if ($log && $deleted > 0) {
            $this->activity->log(
                ActivityLogger::USER_SESSIONS_REVOKED,
                "إنهاء {$deleted} جلسة للمستخدم {$user->name}",
                $user,
                ['count' => $deleted],
                $actor,
            );
        }

        return $deleted;
    }

    public function revokeTokens(User $user, User $actor, bool $log = true): int
    {
        $deleted = $user->tokens()->delete();

        if ($log && $deleted > 0) {
            $this->activity->log(
                ActivityLogger::USER_TOKENS_REVOKED,
                "إلغاء {$deleted} رمز وصول للمستخدم {$user->name}",
                $user,
                ['count' => $deleted],
                $actor,
            );
        }

        return $deleted;
    }

    private function applyStatus(User $user, string $status, ?string $reason = null): void
    {
        $user->status = $status;

        if ($status === User::STATUS_SUSPENDED) {
            $user->suspended_at = now();
            $user->suspended_reason = $reason;

            return;
        }

        $user->suspended_at = null;
        $user->suspended_reason = null;
    }

    private function deviceLabel(?string $userAgent): string
    {
        if (blank($userAgent)) {
            return 'جهاز غير معروف';
        }

        $platform = match (true) {
            Str::contains($userAgent, 'Windows') => 'Windows',
            Str::contains($userAgent, ['iPhone', 'iPad']) => 'iOS',
            Str::contains($userAgent, 'Android') => 'Android',
            Str::contains($userAgent, 'Macintosh') => 'macOS',
            Str::contains($userAgent, ['Linux', 'X11']) => 'Linux',
            default => 'نظام غير معروف',
        };

        $browser = match (true) {
            Str::contains($userAgent, 'Edg/') => 'Edge',
            Str::contains($userAgent, 'OPR/') => 'Opera',
            Str::contains($userAgent, 'Chrome') => 'Chrome',
            Str::contains($userAgent, 'Firefox') => 'Firefox',
            Str::contains($userAgent, 'Safari') => 'Safari',
            default => 'متصفح غير معروف',
        };

        return "{$browser} — {$platform}";
    }
}
