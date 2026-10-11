<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Currency extends Model
{
    protected $fillable = [
        'code',
        'name',
        'symbol',
        'tag',
        'is_base',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_base' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function exchangeRate(): HasOne
    {
        return $this->hasOne(ExchangeRate::class);
    }

    public function shiftAccounts(): HasMany
    {
        return $this->hasMany(ShiftAccount::class);
    }

    public function exchangeTransactionsAsCurrency(): HasMany
    {
        return $this->hasMany(ExchangeTransaction::class, 'currency_id');
    }

    public function exchangeTransactionsAsCounterCurrency(): HasMany
    {
        return $this->hasMany(ExchangeTransaction::class, 'counter_currency_id');
    }
}
