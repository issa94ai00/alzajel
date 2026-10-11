<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Permissions;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::USERS_VIEW);
    }

    public function view(User $user, User $model): bool
    {
        return $user->can(Permissions::USERS_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::USERS_CREATE);
    }

    public function update(User $user, User $model): bool
    {
        return $user->can(Permissions::USERS_UPDATE)
            && $this->mayTouch($user, $model);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->can(Permissions::USERS_DELETE)
            && $user->id !== $model->id
            && $this->mayTouch($user, $model)
            && ! $this->isLastActiveAdmin($model);
    }

    public function restore(User $user, User $model): bool
    {
        return $user->can(Permissions::USERS_DELETE) && $this->mayTouch($user, $model);
    }

    /**
     * تعطيل/إيقاف حساب — نفس قيود التعديل، مع منع المستخدم من إيقاف نفسه
     * أو إيقاف آخر مدير نشِط في النظام.
     */
    public function changeStatus(User $user, User $model): bool
    {
        return $user->can(Permissions::USERS_UPDATE)
            && $user->id !== $model->id
            && $this->mayTouch($user, $model)
            && ! $this->isLastActiveAdmin($model);
    }

    /**
     * إسناد الأدوار والصلاحيات — لا يجوز لأحد أن يوسّع صلاحيات نفسه.
     */
    public function manageRoles(User $user, User $model): bool
    {
        return $user->can(Permissions::USERS_MANAGE_ROLES)
            && $user->id !== $model->id
            && $this->mayTouch($user, $model);
    }

    public function resetPassword(User $user, User $model): bool
    {
        return $user->can(Permissions::USERS_RESET_PASSWORD) && $this->mayTouch($user, $model);
    }

    public function manageSessions(User $user, User $model): bool
    {
        return $user->can(Permissions::USERS_MANAGE_SESSIONS) && $this->mayTouch($user, $model);
    }

    public function viewActivity(User $user, User $model): bool
    {
        return $user->can(Permissions::ACTIVITY_VIEW);
    }

    /**
     * حساب المدير الأعلى لا يعبث به إلا مدير أعلى مثله.
     */
    private function mayTouch(User $user, User $model): bool
    {
        if (! $model->isSuperAdmin()) {
            return true;
        }

        return $user->isSuperAdmin();
    }

    /**
     * يمنع إقصاء آخر مدير نشِط، وإلا أُغلق النظام على نفسه.
     */
    private function isLastActiveAdmin(User $model): bool
    {
        if (! $model->isSuperAdmin() || ! $model->isActive()) {
            return false;
        }

        return User::query()
            ->role(Permissions::SUPER_ROLE)
            ->where('status', User::STATUS_ACTIVE)
            ->where('id', '!=', $model->id)
            ->doesntExist();
    }
}
