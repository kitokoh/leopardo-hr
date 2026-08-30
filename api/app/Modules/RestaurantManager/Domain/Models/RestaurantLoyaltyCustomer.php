<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\RestaurantLoyaltyCustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Solde de points d'un client fidélité (RESTO-212, issue #6177).
 *
 * Un seul compte par (tenant, contact client) ; `tier_code` identifie le
 * palier (bronze, silver, gold...) par valeur, sans table dédiée.
 */
class RestaurantLoyaltyCustomer extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<RestaurantLoyaltyCustomerFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'customer_contact_id',
        'points',
        'tier_code',
    ];

    protected $attributes = [
        'points' => 0,
    ];

    protected $casts = [
        'points' => 'integer',
    ];

    /**
     * @return HasMany<RestaurantLoyaltyPointsMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(RestaurantLoyaltyPointsMovement::class, 'customer_id');
    }
}
