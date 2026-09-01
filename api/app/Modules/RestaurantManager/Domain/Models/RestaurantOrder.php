<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Modules\RestaurantManager\Domain\Enums\OrderSource;
use App\Modules\RestaurantManager\Domain\Enums\OrderStatus;
use App\Modules\RestaurantManager\Domain\Enums\OrderType;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\RestaurantOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * Commande restaurant (RESTO-208, issue #6173).
 *
 * `reference` est générée automatiquement (`RST-…`) et `idempotency_key`
 * (uuid) si absentes à la création — la paire (tenant, référence) et
 * (tenant, idempotency_key) sont uniques. Totaux en minor units ; `version`
 * protège les mises à jour concurrentes.
 */
class RestaurantOrder extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<RestaurantOrderFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'pos_session_id',
        'reference',
        'order_type',
        'table_id',
        'zone_id',
        'covers',
        'customer_contact_id',
        'rider_id',
        'status',
        'subtotal_minor',
        'tax_minor',
        'discount_minor',
        'total_minor',
        'currency',
        'source',
        'note_redacted',
        'idempotency_key',
        'version',
    ];

    protected $attributes = [
        'status' => 'draft',
        'order_type' => 'dine_in',
        'source' => 'pos',
        'currency' => 'DZD',
        'subtotal_minor' => 0,
        'tax_minor' => 0,
        'discount_minor' => 0,
        'total_minor' => 0,
        'version' => 1,
    ];

    protected $casts = [
        'order_type' => OrderType::class,
        'covers' => 'integer',
        'status' => OrderStatus::class,
        'subtotal_minor' => 'integer',
        'tax_minor' => 'integer',
        'discount_minor' => 'integer',
        'total_minor' => 'integer',
        'source' => OrderSource::class,
        'version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $order): void {
            if (empty($order->reference)) {
                $order->reference = self::generateReference();
            }

            if (empty($order->idempotency_key)) {
                $order->idempotency_key = (string) Str::uuid();
            }
        });
    }

    public static function generateReference(): string
    {
        return 'RST-'.strtoupper(Str::random(10));
    }

    /**
     * @return BelongsTo<RestaurantBranch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }

    /**
     * @return BelongsTo<RestaurantPosSession, $this>
     */
    public function posSession(): BelongsTo
    {
        return $this->belongsTo(RestaurantPosSession::class, 'pos_session_id');
    }

    /**
     * @return BelongsTo<RestaurantTable, $this>
     */
    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id');
    }

    /**
     * @return BelongsTo<RestaurantZone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(RestaurantZone::class, 'zone_id');
    }

    /**
     * @return HasMany<RestaurantOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(RestaurantOrderItem::class, 'order_id');
    }

    /**
     * @return HasMany<RestaurantOrderPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(RestaurantOrderPayment::class, 'order_id');
    }

    /**
     * @return HasMany<RestaurantRefund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(RestaurantRefund::class, 'order_id');
    }

    /**
     * @return HasOne<RestaurantDelivery, $this>
     */
    public function delivery(): HasOne
    {
        return $this->hasOne(RestaurantDelivery::class, 'order_id');
    }
}
