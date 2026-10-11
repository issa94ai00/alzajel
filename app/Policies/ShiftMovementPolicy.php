<?php

namespace App\Policies;

use App\Models\ShiftMovement;
use App\Models\User;
use App\Support\Permissions;

class ShiftMovementPolicy
{
    /**
     * الحذف الناعم: صلاحية الحركات النقدية، على حركة ورديتها ما تزال مفتوحة
     * — لأن الحذف يعكس أثرها على رصيد الدرج.
     */
    public function delete(User $user, ShiftMovement $movement): bool
    {
        return $user->can(Permissions::SHIFTS_DEPOSIT)
            && $this->reachable($user, $movement)
            && ! $movement->trashed()
            && $movement->isDeletable();
    }

    public function restore(User $user, ShiftMovement $movement): bool
    {
        return $user->can(Permissions::SHIFTS_DEPOSIT)
            && $this->reachable($user, $movement)
            && $movement->trashed()
            && $movement->isDeletable();
    }

    /**
     * الحركة تخصّ وردية؛ فمن يرى الوردية يرى حركاتها.
     */
    private function reachable(User $user, ShiftMovement $movement): bool
    {
        return $movement->shift?->opened_by === $user->id
            || $user->can(Permissions::SHIFTS_VIEW_ANY);
    }
}
