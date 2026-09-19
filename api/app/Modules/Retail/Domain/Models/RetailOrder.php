<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Models;

use App\Modules\Retail\Domain\Enums\RetailOrderSource;
use App\Modules\Retail\Domain\Enums\RetailOrderStatus;
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
