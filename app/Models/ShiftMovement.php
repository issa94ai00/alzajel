<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * حركة نقدية يدوية على درج الوردية: إيداع أو سحب.
 *
 * تقابل عملية الصرافة في أثرها على الرصيد، وتفارقها في أنها بلا سعر ولا
 * عملة مقابلة ولا ربح — فلا تدخل حسابات الأرباح والخسائر.
 */
class ShiftMovement extends Model
{
    use SoftDeletes;

    public const TYPE_DEPOSIT = 'deposit';

    public const TYPE_WITHDRAWAL = 'withdrawal';

    /**
     * @var array<string, string>
     */
    public const TYPE_LABELS = [
        self::TYPE_DEPOSIT => 'إيداع',
        self::TYPE_WITHDRAWAL => 'سحب',
    ];

    protected $fillable = [
        'shift_id',
        'currency_id',
        'user_id',
        'type',
        'amount',
        'notes',
        'deleted_by',
        'deletion_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function isDeposit(): bool
    {
        return $this->type === self::TYPE_DEPOSIT;
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    /**
     * إشارة الحركة على الرصيد: الإيداع يزيد والسحب ينقص.
     */
    public function signedAmount(): string
    {
        return $this->isDeposit()
            ? (string) $this->amount
            : bcmul((string) $this->amount, '-1', 4);
    }

    /**
     * الحركة قابلة للحذف ما دامت ورديتها مفتوحة — كالعمليات تماماً: الحذف
     * يعكس أثرها على الرصيد، ورصيد الوردية المغلقة صار رصيد افتتاح التالية.
     */
    public function isDeletable(): bool
    {
        return $this->shift?->isOpen() ?? false;
    }

    public function scopeTrashedFilter(Builder $query, ?string $scope): Builder
    {
        return match ($scope) {
            'only' => $query->onlyTrashed(),
            'with' => $query->withTrashed(),
            default => $query,
        };
    }
}
