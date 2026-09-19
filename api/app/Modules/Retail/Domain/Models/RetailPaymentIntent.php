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
 * Intent de paiement en ligne d'une commande marketplace
 * (BC-17 RETAIL, #7812).
 *
 * Un seul intent `pending` par commande (index unique partiel Postgres) —
 * le rejeu de la demande retourne l'intent existant. `provider_reference`
 * unique par tenant : c'est la clé de réconciliation du webhook signé
 * (transition `pending → paid|failed`, idempotente au rejeu). Montants en
 * minor units. Écritures UNIQUEMENT via RetailOnlinePaymentService
 * (transaction).
 *
 * @property int $id
 * @property string $company_id
 * @property int $order_id
 * @property string $provider
 * @property RetailPaymentIntentStatus $status
 * @property int $amount_minor
 * @property string $currency
 * @property string $provider_reference
 * @property string|null $checkout_url
 * @property string|null $failure_reason
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class RetailPaymentIntent extends Model
{
    use BelongsToCompany;

    protected $table = 'retail_payment_intents';

    protected $fillable = [
        'company_id',
        'order_id',
        'provider',
        'status',
        'amount_minor',
        'currency',
        'provider_reference',
        'checkout_url',
        'failure_reason',
        'paid_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RetailPaymentIntentStatus::class,
            'amount_minor' => 'integer',
            'paid_at' => 'datetime',
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
