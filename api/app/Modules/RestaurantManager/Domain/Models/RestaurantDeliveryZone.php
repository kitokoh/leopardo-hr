<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Modules\RestaurantManager\Domain\Enums\RestaurantRecordStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\RestaurantDeliveryZoneFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Zone de livraison d'une branche (RESTO-211, issue #6176).
 *
 * Le nom est unique par (tenant, branche) ; `fee_minor` est la tarification
 * de livraison et `min_order_minor` le montant minimum de commande (minor units).
 */
class RestaurantDeliveryZone extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<RestaurantDeliveryZoneFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'fee_minor',
        'min_order_minor',
        'status',
    ];

    protected $attributes = [
        'fee_minor' => 0,
        'status' => 'active',
    ];

    protected $casts = [
        'fee_minor' => 'integer',
        'min_order_minor' => 'integer',
        'status' => RestaurantRecordStatus::class,
    ];

    /**
     * @return BelongsTo<RestaurantBranch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }
}
