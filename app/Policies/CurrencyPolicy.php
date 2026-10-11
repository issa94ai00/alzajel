<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Permissions;

class CurrencyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::CURRENCIES_MANAGE);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::CURRENCIES_MANAGE);
    }

    public function update(User $user): bool
    {
        return $user->can(Permissions::CURRENCIES_MANAGE);
    }
}
