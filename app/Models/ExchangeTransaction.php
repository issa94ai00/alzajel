<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeTransaction extends Model
{
    public const TYPE_BUY = 'buy';

    public const TYPE_SELL = 'sell';

    protected $fillable = [
        'shift_id',
        'user_id',
        'type',
        'currency_id',
        'counter_currency_id',
        'amount',
        'rate',
        'total',
        'reference_buy_rate',
        'reference_sell_rate',
        'profit',
        'customer_name',
        'customer_phone',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'rate' => 'decimal:4',
            'total' => 'decimal:4',
            'reference_buy_rate' => 'decimal:4',
            'reference_sell_rate' => 'decimal:4',
            'profit' => 'decimal:4',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function counterCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'counter_currency_id');
    }
}
