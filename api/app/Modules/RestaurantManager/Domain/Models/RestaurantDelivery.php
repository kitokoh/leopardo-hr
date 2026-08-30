<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Modules\RestaurantManager\Domain\Enums\DeliveryStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\RestaurantDeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Livraison rattachée à une commande (RESTO-211, issue #6176).
 *
 * `zone_id`/`rider_id` optionnels jusqu'à l'affectation ; `fee_minor` est la
 * part de livraison facturée (minor units). Une commande n'a qu'une livraison.
 */
class RestaurantDelivery extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<RestaurantDeliveryFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'order_id',
        'zone_id',
        'rider_id',
        'status',
        'fee_minor',
        'delivered_at',
        'delivered_to_contact',
    ];

    protected $casts = [
        'status' => DeliveryStatus::class,
        'fee_minor' => 'integer',
        'delivered_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<RestaurantOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(RestaurantOrder::class, 'order_id');
    }

    /**
     * @return BelongsTo<RestaurantDeliveryZone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(RestaurantDeliveryZone::class, 'zone_id');
    }

    /**
     * @return BelongsTo<RestaurantDeliveryRider, $this>
     */
    public function rider(): BelongsTo
    {
        return $this->belongsTo(RestaurantDeliveryRider::class, 'rider_id');
    }
}
