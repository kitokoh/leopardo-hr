<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Modules\RestaurantManager\Domain\Enums\RestaurantRecordStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\RestaurantBranchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Succursale (point de vente) de la verticale RestaurantManager (RESTO-201, issue #6166).
 *
 * `code` est unique par tenant ; `timezone` et `currency` servent de valeurs
 * par défaut aux entités rattachées (zones, tables, commandes).
 */
class RestaurantBranch extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<RestaurantBranchFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'address',
        'city',
        'phone',
        'timezone',
        'currency',
        'status',
    ];

    protected $attributes = [
        'status' => 'active',
        'timezone' => 'UTC',
        'currency' => 'DZD',
    ];

    protected $casts = [
        'status' => RestaurantRecordStatus::class,
    ];

    /**
     * @return HasMany<RestaurantZone, $this>
     */
    public function zones(): HasMany
    {
        return $this->hasMany(RestaurantZone::class, 'branch_id');
    }

    /**
     * @return HasMany<RestaurantTable, $this>
     */
    public function tables(): HasMany
    {
        return $this->hasMany(RestaurantTable::class, 'branch_id');
    }

    /**
     * @return HasMany<RestaurantHour, $this>
     */
    public function hours(): HasMany
    {
        return $this->hasMany(RestaurantHour::class, 'branch_id');
    }

    /**
     * @return HasMany<RestaurantCategory, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(RestaurantCategory::class, 'branch_id');
    }

    /**
     * @return HasMany<RestaurantProduct, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(RestaurantProduct::class, 'branch_id');
    }

    /**
     * @return HasMany<RestaurantMenu, $this>
     */
    public function menus(): HasMany
    {
        return $this->hasMany(RestaurantMenu::class, 'branch_id');
    }

    /**
     * @return HasMany<RestaurantStockLevel, $this>
     */
    public function stockLevels(): HasMany
    {
        return $this->hasMany(RestaurantStockLevel::class, 'branch_id');
    }

    /**
     * @return HasMany<RestaurantPosSession, $this>
     */
    public function posSessions(): HasMany
    {
        return $this->hasMany(RestaurantPosSession::class, 'branch_id');
    }

    /**
     * @return HasMany<RestaurantOrder, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(RestaurantOrder::class, 'branch_id');
    }

    /**
     * @return HasMany<RestaurantReservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(RestaurantReservation::class, 'branch_id');
    }

    /**
     * @return HasMany<RestaurantDeliveryZone, $this>
     */
    public function deliveryZones(): HasMany
    {
        return $this->hasMany(RestaurantDeliveryZone::class, 'branch_id');
    }

    /**
     * @return HasMany<RestaurantDeliveryRider, $this>
     */
    public function deliveryRiders(): HasMany
    {
        return $this->hasMany(RestaurantDeliveryRider::class, 'branch_id');
    }
}
