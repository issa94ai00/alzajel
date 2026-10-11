<?php

namespace App\Policies;

use App\Models\ExchangeTransaction;
use App\Models\User;
use App\Support\Permissions;

class ExchangeTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::TRANSACTIONS_VIEW);
    }

    public function view(User $user, ExchangeTransaction $exchangeTransaction): bool
    {
        if (! $user->can(Permissions::TRANSACTIONS_VIEW)) {
            return false;
        }

        return $exchangeTransaction->user_id === $user->id
            || $user->can(Permissions::TRANSACTIONS_VIEW_ANY_USER);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::TRANSACTIONS_CREATE);
    }

    /**
     * الحذف الناعم: صلاحية الحذف، على عملية يملك صاحبُها رؤيتَها، وورديتها
     * ما تزال مفتوحة — لأن الحذف يعكس أثرها على أرصدة الوردية.
     */
    public function delete(User $user, ExchangeTransaction $exchangeTransaction): bool
    {
        return $user->can(Permissions::TRANSACTIONS_DELETE)
            && $this->reachable($user, $exchangeTransaction)
            && ! $exchangeTransaction->trashed()
            && $exchangeTransaction->isDeletable();
    }

    public function restore(User $user, ExchangeTransaction $exchangeTransaction): bool
    {
        return $user->can(Permissions::TRANSACTIONS_DELETE)
            && $this->reachable($user, $exchangeTransaction)
            && $exchangeTransaction->trashed()
            // ما حُذف تبعاً للوردية يعود باستعادتها، لا بزرّ استعادة مستقل.
            && ! $exchangeTransaction->deleted_with_shift
            && $exchangeTransaction->isDeletable();
    }

    /**
     * استعراض سلّة المحذوفات — نفس صلاحية الحذف.
     */
    public function viewTrashed(User $user): bool
    {
        return $user->can(Permissions::TRANSACTIONS_DELETE);
    }

    private function reachable(User $user, ExchangeTransaction $exchangeTransaction): bool
    {
        return $exchangeTransaction->user_id === $user->id
            || $user->can(Permissions::TRANSACTIONS_VIEW_ANY_USER);
    }
}
