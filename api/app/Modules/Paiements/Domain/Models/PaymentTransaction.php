<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Domain\Models;

use App\Modules\Paiements\Domain\Enums\PaymentTransactionStatus;
use App\Modules\Paiements\Domain\Enums\PaymentTransactionType;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * BC-38 PAIEMENTS (#8680) — transaction financiere.
 *
 * `provider_instance_id` est **nullable** : un transfert strictement interne
 * n'a aucun rail externe. `idempotency_key` est unique par tenant, en base.
 *
 * @property int $id
 * @property string $company_id
 * @property PaymentTransactionType $type
 * @property PaymentTransactionStatus $status
 * @property int|null $provider_instance_id
 * @property int|null $wallet_id
 * @property int|null $counterparty_wallet_id
 * @property int $amount
 * @property int $fee_amount
 * @property string $currency
 * @property string $idempotency_key
 * @property string|null $external_reference
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class PaymentTransaction extends Model
{
    use BelongsToCompany;

    protected $table = 'payment_transactions';

    protected $fillable = [
        'company_id',
        'type',
        'status',
        'provider_instance_id',
        'wallet_id',
        'counterparty_wallet_id',
        'amount',
        'fee_amount',
        'currency',
        'idempotency_key',
        'external_reference',
        'metadata',
    ];

    protected $casts = [
        'type' => PaymentTransactionType::class,
        'status' => PaymentTransactionStatus::class,
        'amount' => 'integer',
        'fee_amount' => 'integer',
        'metadata' => 'array',
    ];

    /** @return HasMany<PaymentLedgerEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(PaymentLedgerEntry::class, 'transaction_id');
    }

    /** @return BelongsTo<Wallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'wallet_id');
    }

    /** @return BelongsTo<PaymentProviderInstance, $this> */
    public function providerInstance(): BelongsTo
    {
        return $this->belongsTo(PaymentProviderInstance::class, 'provider_instance_id');
    }

    /**
     * Transition de statut controlee par la machine a etats.
     *
     * Aucun appelant ne doit ecrire `status` directement : passer par ici
     * garantit qu'un `succeeded` ne redevient jamais `pending`.
     */
    public function transitionTo(PaymentTransactionStatus $target): void
    {
        $this->status->assertCanTransitionTo($target);

        $this->status = $target;
        $this->save();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOfStatus(Builder $query, PaymentTransactionStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }
}
