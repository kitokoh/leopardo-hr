<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Modules\RestaurantManager\Domain\Enums\LoyaltyPointsReason;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\RestaurantLoyaltyPointsMovementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mouvement de points fidélité (gain, échange, ajustement, expiration) — RESTO-212, issue #6177.
 *
 * `delta` est signé (négatif = débit) ; `reason_code` est un code contrôlé.
 * `order_id`/`reference_id` tracent l'événement source (optionnel).
 */
class RestaurantLoyaltyPointsMovement extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<RestaurantLoyaltyPointsMovementFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'customer_id',
        'delta',
        'reason_code',
        'order_id',
        'reference_id',
    ];

    protected $casts = [
        'delta' => 'integer',
        'reason_code' => LoyaltyPointsReason::class,
    ];

    /**
     * @return BelongsTo<RestaurantLoyaltyCustomer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(RestaurantLoyaltyCustomer::class, 'customer_id');
    }

    /**
     * @return BelongsTo<RestaurantOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(RestaurantOrder::class, 'order_id');
    }
}
