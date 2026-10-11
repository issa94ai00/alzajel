<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Services\ActivityLogger;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activity,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Role::class);

        $actor = $request->user();

        $roles = Role::with('permissions:id,name')
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'is_system' => in_array($role->name, Permissions::SYSTEM_ROLES, true),
                'is_super' => $role->name === Permissions::SUPER_ROLE,
                'users_count' => $role->users_count,
                // دور المدير يملك كل شيء ضمنياً، فنعرضه كذلك مهما كان المخزَّن.
                'permissions' => $role->name === Permissions::SUPER_ROLE
                    ? Permissions::all()
                    : $role->permissions->pluck('name')->all(),
                'can' => [
                    'update' => $actor->can('update', $role),
                    'delete' => $actor->can('delete', $role),
                ],
            ]);

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles,
            'permissionGroups' => collect(Permissions::groups())
                ->map(fn (array $group, string $key) => [
                    'key' => $key,
                    'label' => $group['label'],
                    'permissions' => collect($group['permissions'])
                        ->map(fn (string $label, string $name) => ['name' => $name, 'label' => $label])
                        ->values()
                        ->all(),
                ])
                ->values(),
            'can' => [
                'create' => $actor->can('create', Role::class),
            ],
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = Role::create(['name' => $request->validated('name'), 'guard_name' => 'web']);
        $role->syncPermissions($request->validated('permissions'));

        $this->activity->log(
            ActivityLogger::ROLE_CREATED,
            "إنشاء الدور «{$role->name}»",
            $role,
            ['permissions' => $request->validated('permissions')],
            $request->user(),
        );

        return back()->with('success', 'تم إنشاء الدور بنجاح.');
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $before = [
            'name' => $role->name,
            'permissions' => $role->permissions->pluck('name')->sort()->values()->all(),
        ];

        if ($name = $request->validated('name')) {
            $role->update(['name' => $name]);
        }

        $role->syncPermissions($request->validated('permissions'));

        $after = [
            'name' => $role->fresh()->name,
            'permissions' => collect($request->validated('permissions'))->sort()->values()->all(),
        ];

        $this->activity->log(
            ActivityLogger::ROLE_UPDATED,
            "تعديل الدور «{$role->name}»",
            $role,
            ['changes' => $this->activity->diff($before, $after)],
            $request->user(),
        );

        return back()->with('success', 'تم تحديث الدور بنجاح.');
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        $name = $role->name;
        $role->delete();

        $this->activity->log(
            ActivityLogger::ROLE_DELETED,
            "حذف الدور «{$name}»",
            null,
            ['name' => $name],
            $request->user(),
        );

        return back()->with('success', 'تم حذف الدور بنجاح.');
    }
}
