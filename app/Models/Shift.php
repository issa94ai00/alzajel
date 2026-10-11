<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shift extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'opened_by',
        'closed_by',
        'status',
        'business_date',
        'opened_at',
        'closed_at',
        'notes',
        'deleted_by',
        'deletion_reason',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date:Y-m-d',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function shiftAccounts(): HasMany
    {
        return $this->hasMany(ShiftAccount::class);
    }

    public function exchangeTransactions(): HasMany
    {
        return $this->hasMany(ExchangeTransaction::class);
    }

    /**
     * الحركات النقدية اليدوية — إيداع وسحب، خارج عمليات الصرافة.
     */
    public function movements(): HasMany
    {
        return $this->hasMany(ShiftMovement::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * الوردية المفتوحة لا تُحذف — تُغلق أولاً.
     *
     * سببان: صندوقها ما يزال يتحرّك فلا معنى لأرشفته، والقفل الفريد
     * shifts_user_open_lock_unique مبنيّ على عمود افتراضي لا يرى deleted_at،
     * فتبقى وردية محذوفة حاجزةً صاحبَها عن فتح وردية جديدة.
     */
    public function isDeletable(): bool
    {
        return ! $this->isOpen();
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
