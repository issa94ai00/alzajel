<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Permissions;

class ExchangeRatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::RATES_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::RATES_UPDATE);
    }

    public function update(User $user): bool
    {
        return $user->can(Permissions::RATES_UPDATE);
    }
}
