<?php

namespace App\Models;

use App\Support\Permissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ReportDefinition extends Model
{
    protected $fillable = [
        'key',
        'builder',
        'name',
        'description',
        'is_enabled',
        'admin_only',
        'sort_order',
        'options',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'admin_only' => 'boolean',
            'options' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user?->can(Permissions::REPORTS_PROFIT_LOSS)) {
            return $query;
        }

        return $query->where('admin_only', false);
    }

    public function isVisibleTo(?User $user): bool
    {
        return $this->is_enabled && (! $this->admin_only || ($user?->can(Permissions::REPORTS_PROFIT_LOSS) ?? false));
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return data_get($this->options ?? [], $key, $default);
    }
}
