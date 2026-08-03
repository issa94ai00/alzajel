<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftAccount extends Model
{
    protected $fillable = [
        'shift_id',
        'currency_id',
        'opening_balance',
        'current_balance',
        'closing_balance',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:4',
            'current_balance' => 'decimal:4',
            'closing_balance' => 'decimal:4',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
