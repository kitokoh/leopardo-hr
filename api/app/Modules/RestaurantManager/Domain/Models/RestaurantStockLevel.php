<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\RestaurantStockLevelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Niveau de stock courant d'un ingrédient dans une branche (RESTO-205, issue #6170).
 *
 * Un seul niveau par (tenant, branche, ingrédient) ; `reorder_level` et
 * `alert_threshold` déclenchent les alertes de réapprovisionnement.
 */
class RestaurantStockLevel extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<RestaurantStockLevelFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'ingredient_id',
        'quantity',
        'avg_cost_minor',
        'reorder_level',
        'alert_threshold',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'avg_cost_minor' => 'integer',
        'reorder_level' => 'decimal:3',
        'alert_threshold' => 'decimal:3',
    ];

    /**
     * @return BelongsTo<RestaurantBranch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }

    /**
     * @return BelongsTo<RestaurantIngredient, $this>
     */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(RestaurantIngredient::class, 'ingredient_id');
    }
}
