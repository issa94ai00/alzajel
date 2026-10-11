<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_INACTIVE = 'inactive';

    /**
     * @var array<string, string>
     */
    public const STATUS_LABELS = [
        self::STATUS_ACTIVE => 'نشط',
        self::STATUS_SUSPENDED => 'موقوف',
        self::STATUS_INACTIVE => 'غير مفعّل',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'phone',
        'employee_no',
        'notes',
        'must_change_password',
        'password_changed_at',
        'last_login_at',
        'last_login_ip',
        'suspended_at',
        'suspended_reason',
        'created_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_by');
    }

    public function openedShifts(): HasMany
    {
        return $this->hasMany(Shift::class, 'opened_by');
    }

    public function exchangeTransactions(): HasMany
    {
        return $this->hasMany(ExchangeTransaction::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'causer_id');
    }

    /**
     * الوردية المفتوحة الخاصة بهذا المستخدم — كل مستخدم له صندوقه.
     */
    public function openShift(): ?Shift
    {
        return $this->openedShifts()->where('status', 'open')->first();
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(\App\Support\Permissions::SUPER_ROLE);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /**
     * سبب منع الدخول، أو null إن كان الحساب مسموحاً له بالدخول.
     */
    public function loginBlockReason(): ?string
    {
        return match ($this->status) {
            self::STATUS_SUSPENDED => 'تم إيقاف هذا الحساب. يرجى مراجعة مدير النظام.',
            self::STATUS_INACTIVE => 'هذا الحساب غير مفعّل بعد. يرجى مراجعة مدير النظام.',
            default => null,
        };
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('employee_no', 'like', "%{$term}%");
        });
    }
}
