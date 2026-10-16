<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    protected $fillable = [
        'id',
        'company_name',
        'status',
        'plan_id',
        'trial_ends_at',
        'data',
    ];

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'company_name',
            'status',
            'plan_id',
            'trial_ends_at',
            'created_at',
            'updated_at',
        ];
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function invoices()
    {
        return $this->hasMany(CentralInvoice::class);
    }
}
