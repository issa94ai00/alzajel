<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExchangeTransaction extends Model
{
    use SoftDeletes;

    public const TYPE_BUY = 'buy';

    public const TYPE_SELL = 'sell';

    protected $fillable = [
        'shift_id',
        'user_id',
        'type',
        'currency_id',
        'counter_currency_id',
        'currency_tag',
        'amount',
        'rate',
        'total',
        'reference_buy_rate',
        'reference_sell_rate',
        'profit',
        'customer_name',
        'customer_phone',
        'notes',
        'deleted_by',
        'deletion_reason',
        'deleted_with_shift',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            // الأسعار نِسَب تحتاج خانات أكثر من المبالغ — انظر هجرة توسيع الدقّة.
            'rate' => 'decimal:10',
            'total' => 'decimal:4',
            'reference_buy_rate' => 'decimal:10',
            'reference_sell_rate' => 'decimal:10',
            'profit' => 'decimal:4',
            'deleted_with_shift' => 'boolean',
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

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * العملية قابلة للحذف ما دامت ورديتها مفتوحة.
     *
     * عكسُ أثر العملية يعني تعديل رصيد الوردية، ورصيدُ الوردية المغلقة صار
     * رصيدَ افتتاح التالية — فتعديله بأثر رجعي يكسر السلسلة كلها.
     */
    public function isDeletable(): bool
    {
        return $this->shift?->isOpen() ?? false;
    }

    /**
     * نطاق الحذف: السارية فقط، أو المحذوفة فقط، أو الاثنتان.
     */
    public function scopeTrashedFilter(Builder $query, ?string $scope): Builder
    {
        return match ($scope) {
            'only' => $query->onlyTrashed(),
            'with' => $query->withTrashed(),
            default => $query,
        };
    }
}
