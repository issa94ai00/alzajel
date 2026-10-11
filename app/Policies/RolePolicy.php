<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Permissions;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::ROLES_MANAGE);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::ROLES_MANAGE);
    }

    /**
     * دور المدير الأعلى يملك كل شيء دائماً — تعديله يعني كسر النظام.
     */
    public function update(User $user, Role $role): bool
    {
        return $user->can(Permissions::ROLES_MANAGE) && $role->name !== Permissions::SUPER_ROLE;
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->can(Permissions::ROLES_MANAGE)
            && ! in_array($role->name, Permissions::SYSTEM_ROLES, true)
            && $role->users()->doesntExist();
    }
}
