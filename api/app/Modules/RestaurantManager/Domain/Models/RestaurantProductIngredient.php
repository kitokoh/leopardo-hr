<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\RestaurantProductIngredientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne de recette : ingrédient d'un produit avec sa quantité (RESTO-202, issue #6167).
 *
 * Un même ingrédient ne peut apparaître qu'une fois par produit
 * (UNIQUE company_id, product_id, ingredient_id) ; `unit_code` référence
 * `restaurant_units.code` (par valeur, sans FK).
 */
class RestaurantProductIngredient extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<RestaurantProductIngredientFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'product_id',
        'ingredient_id',
        'quantity',
        'unit_code',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
    ];

    /**
     * @return BelongsTo<RestaurantProduct, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(RestaurantProduct::class, 'product_id');
    }

    /**
     * @return BelongsTo<RestaurantIngredient, $this>
     */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(RestaurantIngredient::class, 'ingredient_id');
    }
}
