<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Modules\RestaurantManager\Domain\Enums\RestaurantRecordStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\RestaurantIngredientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ingrédient (matière première) utilisé dans les recettes — RESTO-203, issue #6168.
 *
 * `unit_code` référence `restaurant_units.code` (par valeur) ; `code` est
 * unique par (tenant, branche). `avg_cost_minor` sert au calcul du coût
 * théorique des recettes.
 */
class RestaurantIngredient extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<RestaurantIngredientFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'code',
        'name',
        'unit_code',
        'avg_cost_minor',
        'status',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    protected $casts = [
        'avg_cost_minor' => 'integer',
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
     * @return HasMany<RestaurantStockLevel, $this>
     */
    public function stockLevels(): HasMany
    {
        return $this->hasMany(RestaurantStockLevel::class, 'ingredient_id');
    }
}
