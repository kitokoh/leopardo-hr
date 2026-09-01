<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Modules\RestaurantManager\Domain\Enums\RestaurantRecordStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\RestaurantCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Categorie de produits (plats, boissons, desserts...) — RESTO-202, issue #6167.
 *
 * `branch_id` null signifie que la categorie s'applique a toutes les branches
 * du tenant ; le nom est unique par (tenant, branche, nom).
 */
class RestaurantCategory extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<RestaurantCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'color',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'sort_order' => 'integer',
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
     * Produits de la catégorie (RESTO-805/#6226 — menu public).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<RestaurantProduct, $this>
     */
    public function products(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RestaurantProduct::class, 'category_id');
    }
}
