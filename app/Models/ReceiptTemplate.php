<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ReceiptTemplate extends Model
{
    public const SUBJECT_TRANSACTION = 'transaction';

    public const SUBJECT_SHIFT = 'shift';

    protected $fillable = [
        'key',
        'subject',
        'name',
        'paper',
        'is_default',
        'is_active',
        'copies',
        'options',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'copies' => 'integer',
            'options' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForSubject(Builder $query, string $subject): Builder
    {
        return $query->where('subject', $subject);
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return data_get($this->options ?? [], $key, $default);
    }

    /**
     * @return array{label: string, width: string, font: string}
     */
    public function paperSpec(): array
    {
        return config("sahli.papers.{$this->paper}") ?? config('sahli.papers.80mm');
    }

    public function isThermal(): bool
    {
        return in_array($this->paper, ['58mm', '80mm'], true);
    }
}
