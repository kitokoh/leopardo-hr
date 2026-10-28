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

    /**
     * Valeurs par defaut alignees sur le schema SQL.
     *
     * Eloquent ne lit JAMAIS les `default()` SQL : juste apres `save()`,
     * l'attribut vaut `null` en memoire tant que la ligne n'a pas ete
     * relue. Les Resources faisaient `$this->status->value` sur ce `null`
     * et repondaient 500 sur toute creation (cf. #8396, meme classe de
     * bug que MarketingAsset).
     *
     * @var array<string, mixed>
     */
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
