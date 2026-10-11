<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResetUserPasswordRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\SyncUserAccessRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UpdateUserStatusRequest;
use App\Models\ActivityLog;
use App\Models\ExchangeTransaction;
use App\Models\Shift;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\UserManager;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * الأعمدة المسموح الترتيب بها — نمنع تمرير أي عمود عشوائي من الواجهة.
     *
     * @var list<string>
     */
    private const SORTABLE = ['name', 'email', 'status', 'last_login_at', 'created_at'];

    public function __construct(
        private readonly UserManager $users,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string', Rule::exists('roles', 'name')],
            'status' => ['nullable', Rule::in(array_keys(User::STATUS_LABELS))],
            'trashed' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(self::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';
        $showTrashed = (bool) ($filters['trashed'] ?? false);

        $users = User::query()
            ->with('roles:id,name')
            ->withCount('exchangeTransactions')
            ->when($showTrashed, fn ($q) => $q->onlyTrashed())
            ->search($filters['search'] ?? null)
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->role($role))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user) => $this->summarize($user, $request->user()));

        $openShiftOwners = Shift::open()->pluck('opened_by')->all();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'role' => $filters['role'] ?? '',
                'status' => $filters['status'] ?? '',
                'trashed' => $showTrashed,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'roles' => Role::orderBy('name')->pluck('name'),
            'statuses' => User::STATUS_LABELS,
            'openShiftOwners' => $openShiftOwners,
            'stats' => [
                'total' => User::count(),
                'active' => User::where('status', User::STATUS_ACTIVE)->count(),
                'suspended' => User::where('status', User::STATUS_SUSPENDED)->count(),
                'inactive' => User::where('status', User::STATUS_INACTIVE)->count(),
                'trashed' => User::onlyTrashed()->count(),
                'open_shifts' => count($openShiftOwners),
            ],
            'can' => [
                'create' => $request->user()->can('create', User::class),
                'manageRoles' => $request->user()->can(Permissions::ROLES_MANAGE),
                'viewActivity' => $request->user()->can(Permissions::ACTIVITY_VIEW),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Admin/Users/Create', [
            'roles' => $this->assignableRoles($request->user()),
            'statuses' => User::STATUS_LABELS,
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = $this->users->create($request->validated(), $request->user());

        return redirect()->route('users.show', $user)->with('success', 'تم إنشاء المستخدم بنجاح.');
    }

    public function show(Request $request, User $user): Response
    {
        $this->authorize('view', $user);

        $actor = $request->user();

        return Inertia::render('Admin/Users/Show', [
            'targetUser' => $this->detail($user),
            'stats' => $this->stats($user),
            // التسميات مصدرها النموذج، فلا تُكرَّر في الواجهة وتتباعد عنه.
            'statuses' => User::STATUS_LABELS,
            'roles' => $this->assignableRoles($actor),
            'permissionGroups' => $this->permissionGroups(),
            'effectivePermissions' => $user->getAllPermissions()->pluck('name')->values(),
            'directPermissions' => $user->getDirectPermissions()->pluck('name')->values(),
            'activity' => $actor->can('viewActivity', $user)
                ? ActivityLog::forSubject($user)->with('causer:id,name')->latest('created_at')->limit(30)->get()
                    ->map(fn (ActivityLog $log) => $this->presentLog($log))
                : [],
            'sessions' => $actor->can('manageSessions', $user)
                ? $this->users->sessions($user, $request->session()->getId())->values()
                : [],
            'tokens' => $actor->can('manageSessions', $user)
                ? $this->users->tokens($user)->values()
                : [],
            'can' => [
                'update' => $actor->can('update', $user),
                'delete' => $actor->can('delete', $user),
                'changeStatus' => $actor->can('changeStatus', $user),
                'manageRoles' => $actor->can('manageRoles', $user),
                'resetPassword' => $actor->can('resetPassword', $user),
                'manageSessions' => $actor->can('manageSessions', $user),
                'viewActivity' => $actor->can('viewActivity', $user),
            ],
        ]);
    }

    public function edit(Request $request, User $user): Response
    {
        $this->authorize('update', $user);

        return Inertia::render('Admin/Users/Edit', [
            'targetUser' => $this->detail($user),
            'statuses' => User::STATUS_LABELS,
            'can' => [
                'changeStatus' => $request->user()->can('changeStatus', $user),
            ],
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->users->update($user, $request->validated(), $request->user());

        return redirect()->route('users.show', $user)->with('success', 'تم تحديث بيانات المستخدم بنجاح.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $this->users->delete($user, $request->user());

        return redirect()->route('users.index')->with('success', 'تم حذف المستخدم بنجاح.');
    }

    public function restore(Request $request, int $userId): RedirectResponse
    {
        $user = User::onlyTrashed()->findOrFail($userId);

        $this->authorize('restore', $user);

        $this->users->restore($user, $request->user());

        return redirect()->route('users.show', $user)->with('success', 'تمت استعادة المستخدم بنجاح.');
    }

    public function updateStatus(UpdateUserStatusRequest $request, User $user): RedirectResponse
    {
        $this->users->changeStatus(
            $user,
            $request->validated('status'),
            $request->validated('reason'),
            $request->user(),
        );

        return back()->with('success', 'تم تحديث حالة المستخدم.');
    }

    public function resetPassword(ResetUserPasswordRequest $request, User $user): RedirectResponse
    {
        $this->users->resetPassword(
            $user,
            $request->validated('password'),
            (bool) $request->validated('must_change_password', true),
            $request->user(),
        );

        return back()->with('success', 'تم تعيين كلمة مرور جديدة وإنهاء جلسات المستخدم.');
    }

    public function syncAccess(SyncUserAccessRequest $request, User $user): RedirectResponse
    {
        $this->users->syncAccess(
            $user,
            $request->validated('roles'),
            $request->validated('permissions', []),
            $request->user(),
        );

        return back()->with('success', 'تم تحديث أدوار وصلاحيات المستخدم.');
    }

    public function revokeSessions(Request $request, User $user): RedirectResponse
    {
        $this->authorize('manageSessions', $user);

        // لا نُخرج المشرف من جلسته الحالية إن كان يراجع حسابه هو.
        $except = $request->user()->is($user) ? $request->session()->getId() : null;

        $count = $this->users->revokeSessions($user, $request->user(), $except);

        return back()->with('success', "تم إنهاء {$count} جلسة.");
    }

    public function revokeTokens(Request $request, User $user): RedirectResponse
    {
        $this->authorize('manageSessions', $user);

        $count = $this->users->revokeTokens($user, $request->user());

        return back()->with('success', "تم إلغاء {$count} رمز وصول.");
    }

    /**
     * إجراءات جماعية على مجموعة مستخدمين مختارة من الجدول.
     */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['activate', 'suspend', 'deactivate', 'delete'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', Rule::exists('users', 'id')],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $actor = $request->user();
        $targets = User::whereIn('id', $data['ids'])->get();

        $applied = 0;
        $skipped = 0;

        foreach ($targets as $target) {
            $ability = $data['action'] === 'delete' ? 'delete' : 'changeStatus';

            if ($actor->cannot($ability, $target)) {
                $skipped++;

                continue;
            }

            match ($data['action']) {
                'activate' => $this->users->changeStatus($target, User::STATUS_ACTIVE, null, $actor),
                'suspend' => $this->users->changeStatus($target, User::STATUS_SUSPENDED, $data['reason'] ?? null, $actor),
                'deactivate' => $this->users->changeStatus($target, User::STATUS_INACTIVE, null, $actor),
                'delete' => $this->users->delete($target, $actor),
            };

            $applied++;
        }

        $message = "تم تنفيذ الإجراء على {$applied} مستخدم.";

        if ($skipped > 0) {
            $message .= " تم تخطي {$skipped} حساب لعدم توفر الصلاحية أو لقيود الحماية.";
        }

        return back()->with($applied > 0 ? 'success' : 'error', $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function summarize(User $user, User $actor): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'employee_no' => $user->employee_no,
            'status' => $user->status,
            'status_label' => $user->statusLabel(),
            'roles' => $user->roles->pluck('name'),
            'transactions_count' => $user->exchange_transactions_count,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
            'deleted_at' => $user->deleted_at?->toIso8601String(),
            'is_self' => $actor->is($user),
            'can' => [
                'update' => $actor->can('update', $user),
                'delete' => $actor->can('delete', $user),
                'changeStatus' => $actor->can('changeStatus', $user),
                'restore' => $user->trashed() && $actor->can('restore', $user),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(User $user): array
    {
        $user->loadMissing('roles:id,name', 'createdBy:id,name');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'employee_no' => $user->employee_no,
            'notes' => $user->notes,
            'status' => $user->status,
            'status_label' => $user->statusLabel(),
            'suspended_at' => $user->suspended_at?->toIso8601String(),
            'suspended_reason' => $user->suspended_reason,
            'must_change_password' => $user->must_change_password,
            'password_changed_at' => $user->password_changed_at?->toIso8601String(),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'last_login_ip' => $user->last_login_ip,
            'roles' => $user->roles->pluck('name'),
            'created_by' => $user->createdBy?->name,
            'created_at' => $user->created_at?->toIso8601String(),
            'deleted_at' => $user->deleted_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stats(User $user): array
    {
        $openShift = $user->openShift();

        return [
            'transactions' => ExchangeTransaction::where('user_id', $user->id)->count(),
            'transactions_today' => ExchangeTransaction::where('user_id', $user->id)
                ->whereDate('created_at', today())
                ->count(),
            'shifts' => $user->openedShifts()->count(),
            'open_shift' => $openShift ? [
                'id' => $openShift->id,
                'business_date' => $openShift->business_date->toDateString(),
                'opened_at' => $openShift->opened_at?->toIso8601String(),
            ] : null,
            'last_transaction_at' => ExchangeTransaction::where('user_id', $user->id)
                ->latest('created_at')
                ->value('created_at')?->toIso8601String(),
        ];
    }

    /**
     * الأدوار التي يحق للفاعل إسنادها.
     *
     * @return list<array{name: string, label: string, permissions_count: int}>
     */
    private function assignableRoles(User $actor): array
    {
        return Role::withCount('permissions')
            ->orderBy('name')
            ->get()
            ->reject(fn (Role $role) => $role->name === Permissions::SUPER_ROLE && ! $actor->isSuperAdmin())
            ->map(fn (Role $role) => [
                'name' => $role->name,
                'label' => $role->name,
                'permissions_count' => $role->permissions_count,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{key: string, label: string, permissions: list<array{name: string, label: string}>}>
     */
    private function permissionGroups(): array
    {
        return collect(Permissions::groups())
            ->map(fn (array $group, string $key) => [
                'key' => $key,
                'label' => $group['label'],
                'permissions' => collect($group['permissions'])
                    ->map(fn (string $label, string $name) => ['name' => $name, 'label' => $label])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function presentLog(ActivityLog $log): array
    {
        return [
            'id' => $log->id,
            'event' => $log->event,
            'event_label' => ActivityLogger::label($log->event),
            'description' => $log->description,
            'causer' => $log->causer?->name ?? $log->causer_name,
            'ip_address' => $log->ip_address,
            'properties' => $log->properties,
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }
}
