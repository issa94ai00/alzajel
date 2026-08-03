<?php

namespace App\Policies;

use App\Models\ExchangeTransaction;
use App\Models\User;

class ExchangeTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ExchangeTransaction $exchangeTransaction): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }
}
