<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeRate extends Model
{
    protected $fillable = [
        'currency_id',
        'buy_rate',
        'sell_rate',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'buy_rate' => 'decimal:10',
            'sell_rate' => 'decimal:10',
        ];
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
