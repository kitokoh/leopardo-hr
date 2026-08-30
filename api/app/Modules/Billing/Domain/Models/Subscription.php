<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Models;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Billing\Domain\Enums\SubscriptionStatus;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * @property int $id
 * @property int|null $company_id
 * @property string $plan
 * @property string $status
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $current_period_start
 * @property Carbon|null $current_period_end
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property string|null $payment_method
 * @property int|null $stripe_subscription_id
 * @property int|null $chargily_subscription_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Company|null $company
 *
 * @mixin Builder<static>
 */
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

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Transition d'état gardée (DEP-BC21 #5897/#6246).
     *
     * La machine à états est définie par {@see SubscriptionStatus} : toute
     * transition invalide lève InvalidArgumentException. Une transition vers
     * le statut COURANT est idempotente (sans exception) : elle ne fait que
     * synchroniser les attributs additionnels (périodes, dates…) — c'est le
     * contrat utilisé par les webhooks providers rejoués et les endpoints
     * manager (upgrade/cancel/renew).
     *
     * @param  array<string, mixed>  $extra  attributs additionnels (period_end, cancelled_at…)
     */
    public function transitionTo(SubscriptionStatus $status, array $extra = []): self
    {
        $current = SubscriptionStatus::tryFrom((string) $this->status) ?? SubscriptionStatus::Trial;

        if ($current !== $status && ! $current->canTransitionTo($status)) {
            throw new InvalidArgumentException(
                "Transition de souscription invalide : {$current->value} → {$status->value}"
            );
        }

        $this->forceFill([
            'status' => $status->value,
            ...$extra,
        ])->save();

        return $this;
    }
}
