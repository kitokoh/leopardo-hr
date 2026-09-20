<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Models;

use App\Modules\Retail\Domain\Enums\RetailPaymentIntentStatus;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Intent de paiement en ligne d'une commande marketplace (BC-17 RETAIL,
 * #7812).
 *
 * Cree au checkout public quand `payment_method = online`, il porte la
 * reference publique `intent_reference` (64 hex, unique par tenant et de
 * facto globale) partagee avec le PSP via ses metadata. Le statut n'evolue
 * QUE par RetailPaymentService : webhook signe verifie (fail-closed),
 * reconciliation `retail:payments:reconcile`, ou remboursement vendeur.
 * Montants en minor units. `provider_payload` conserve les reponses
 * provider (checkout, evenement webhook, refund) comme trace auditable.
 *
 * @property int $id
 * @property string $company_id
 * @property int $order_id
 * @property string $intent_reference
 * @property string $provider
 * @property int $amount_minor
 * @property string $currency
 * @property RetailPaymentIntentStatus $status
 * @property string|null $checkout_url
 * @property array<string, mixed>|null $provider_payload
 * @property string|null $idempotency_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class RetailOnlinePaymentIntent extends Model
{
    use BelongsToCompany;

    protected $table = 'retail_online_payment_intents';

    protected $fillable = [
        'company_id',
        'order_id',
        'intent_reference',
        'provider',
        'amount_minor',
        'currency',
        'status',
        'checkout_url',
        'provider_payload',
        'idempotency_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RetailPaymentIntentStatus::class,
            'amount_minor' => 'integer',
            'provider_payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<RetailOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(RetailOrder::class, 'order_id');
    }
}
