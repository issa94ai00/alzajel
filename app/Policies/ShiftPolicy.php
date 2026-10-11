<?php

namespace App\Policies;

use App\Models\Shift;
use App\Models\User;
use App\Support\Permissions;

class ShiftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::SHIFTS_VIEW);
    }

    public function view(User $user, Shift $shift): bool
    {
        if (! $user->can(Permissions::SHIFTS_VIEW)) {
            return false;
        }

        return $this->owns($user, $shift) || $user->can(Permissions::SHIFTS_VIEW_ANY);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::SHIFTS_OPEN);
    }

    /**
     * إغلاق الوردية: إمّا صاحبها ويملك صلاحية إغلاق ورديته، أو مشرف يملك إغلاق ورديات الآخرين.
     */
    public function close(User $user, Shift $shift): bool
    {
        if ($this->owns($user, $shift) && $user->can(Permissions::SHIFTS_CLOSE)) {
            return true;
        }

        return $user->can(Permissions::SHIFTS_CLOSE_ANY);
    }

    /**
     * تسجيل إيداع أو سحب نقدي — على وردية مفتوحة يراها المستخدم.
     *
     * الوردية المغلقة مستبعدة لأن رصيد إغلاقها صار رصيد افتتاح التالية.
     */
    public function recordMovement(User $user, Shift $shift): bool
    {
        return $user->can(Permissions::SHIFTS_DEPOSIT)
            && $this->reachable($user, $shift)
            && $shift->isOpen();
    }

    /**
     * الحذف الناعم: صلاحية الحذف، على وردية يراها المستخدم، بعد إغلاقها.
     */
    public function delete(User $user, Shift $shift): bool
    {
        return $user->can(Permissions::SHIFTS_DELETE)
            && $this->reachable($user, $shift)
            && ! $shift->trashed()
            && $shift->isDeletable();
    }

    public function restore(User $user, Shift $shift): bool
    {
        return $user->can(Permissions::SHIFTS_DELETE)
            && $this->reachable($user, $shift)
            && $shift->trashed();
    }

    /**
     * استعراض سلّة المحذوفات — نفس صلاحية الحذف.
     */
    public function viewTrashed(User $user): bool
    {
        return $user->can(Permissions::SHIFTS_DELETE);
    }

    private function reachable(User $user, Shift $shift): bool
    {
        return $this->owns($user, $shift) || $user->can(Permissions::SHIFTS_VIEW_ANY);
    }

    private function owns(User $user, Shift $shift): bool
    {
        return $shift->opened_by === $user->id;
    }
}
