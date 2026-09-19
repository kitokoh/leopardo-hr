<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Modules\RestaurantManager\Domain\Enums\RestaurantRecordStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\RestaurantProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Produit vendable (plat, boisson, accompagnement) — RESTO-202, issue #6167.
 *
 * `code` est unique par tenant ; les prix sont en unités mineures entières
 * (minor units). `branch_id` null = produit disponible dans toutes les branches.
 *
 * `is_published_online` (RESTO-901/#7746, opt-in) : publication du produit
 * sur le menu du profil public de sa branche (combiné à `is_available`).
 */
class RestaurantProduct extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<RestaurantProductFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'category_id',
        'code',
        'name',
        'description_redacted',
        'price_minor',
        'currency',
        'cost_minor',
        'tax_rate_id',
        'is_available',
        'is_published_online',
        'image_asset_id',
        'status',
    ];

    protected $casts = [
        'price_minor' => 'integer',
        'cost_minor' => 'integer',
        'is_available' => 'boolean',
        'is_published_online' => 'boolean',
        'status' => RestaurantRecordStatus::class,
    ];

    /**
     * @return BelongsTo<RestaurantBranch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }

    /**
     * @return BelongsTo<RestaurantCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(RestaurantCategory::class, 'category_id');
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(RestaurantProductIngredient::class, 'product_id');
    }
}
