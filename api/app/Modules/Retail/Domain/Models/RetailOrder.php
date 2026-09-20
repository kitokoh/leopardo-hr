<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Models;

use App\Modules\Retail\Domain\Enums\RetailFulfillmentStatus;
use App\Modules\Retail\Domain\Enums\RetailOrderSource;
use App\Modules\Retail\Domain\Enums\RetailOrderStatus;
use App\Modules\Retail\Domain\Enums\RetailPaymentMethod;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Commande de vente du module Retail (BC-17 RETAIL, #7674).
 *
 * Reference unique par tenant (`POS-YYYYMMDD-XXXXXX`), `idempotency_key`
 * unique pour le rejeu sans doublon. Totaux TOUJOURS calcules serveur, en
 * minor units. Statut `draft|completed|cancelled` : le passage a `completed`
 * (paiements captures >= total) decremente le stock via RetailStockService.
 * Ecritures UNIQUEMENT via RetailPosService (transaction).
 *
 * Canal en ligne Leopardo Marche (#7808) : commandes invitees
 * (`source = online`, ecritures via RetailOnlineOrderService) avec
 * coordonnees client/livraison nullables (POS), machine d'etats logistique
 * `fulfillment_status`, jeton de suivi public `tracking_token` (64 hex,
 * unique par tenant) et horodatages confirmed/shipped/delivered.
 *
 * @property int $id
 * @property string $company_id
 * @property int $location_id
 * @property int|null $pos_session_id
 * @property string $reference
 * @property RetailOrderStatus $status
 * @property int $subtotal_minor
 * @property int $discount_minor
 * @property int $total_minor
 * @property string $currency
 * @property RetailOrderSource $source
 * @property string|null $note
 * @property string|null $idempotency_key
 * @property string|null $customer_name
 * @property string|null $customer_phone
 * @property string|null $customer_email
 * @property string|null $delivery_address
 * @property string|null $delivery_city
 * @property string|null $delivery_notes
 * @property RetailFulfillmentStatus|null $fulfillment_status
 * @property string|null $tracking_token
 * @property RetailPaymentMethod|null $payment_method
 * @property string|null $payment_status
 * @property Carbon|null $paid_at
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $shipped_at
 * @property Carbon|null $delivered_at
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class RetailOrder extends Model
{
    use BelongsToCompany;

    protected $table = 'retail_orders';

    protected $fillable = [
        'company_id',
        'location_id',
        'pos_session_id',
        'reference',
        'status',
        'subtotal_minor',
        'discount_minor',
        'total_minor',
        'currency',
        'source',
        'note',
        'idempotency_key',
        'customer_name',
        'customer_phone',
        'customer_email',
        'delivery_address',
        'delivery_city',
        'delivery_notes',
        'fulfillment_status',
        'tracking_token',
        'payment_method',
        'payment_status',
        'paid_at',
        'confirmed_at',
        'shipped_at',
        'delivered_at',
        'version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal_minor' => 'integer',
            'discount_minor' => 'integer',
            'total_minor' => 'integer',
            'status' => RetailOrderStatus::class,
            'source' => RetailOrderSource::class,
            'fulfillment_status' => RetailFulfillmentStatus::class,
            'payment_method' => RetailPaymentMethod::class,
            'paid_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<RetailLocation, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(RetailLocation::class, 'location_id');
    }

    /**
     * @return BelongsTo<RetailPosSession, $this>
     */
    public function posSession(): BelongsTo
    {
        return $this->belongsTo(RetailPosSession::class, 'pos_session_id');
    }

    /**
     * @return HasMany<RetailOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(RetailOrderItem::class, 'order_id');
    }

    /**
     * @return HasMany<RetailOrderPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(RetailOrderPayment::class, 'order_id');
    }
}
