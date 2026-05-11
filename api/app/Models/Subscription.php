<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'plan',
        'status',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'cancelled_at',
        'cancel_reason',
        'payment_method',
        'stripe_subscription_id',
        'chargily_subscription_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
