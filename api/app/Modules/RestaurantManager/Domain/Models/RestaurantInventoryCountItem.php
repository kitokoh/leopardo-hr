<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\RestaurantInventoryCountItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne de comptage : écart constaté pour un ingrédient (RESTO-207, issue #6172).
 *
 * `variance_qty` = `counted_qty` − `expected_qty` ; `reason_code` explique
 * l'écart (casse, vol, erreur de saisie...). Un ingrédient ne peut être
 * compté qu'une fois par session (UNIQUE tenant, count, ingrédient).
 */
class RestaurantInventoryCountItem extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<RestaurantInventoryCountItemFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'count_id',
        'ingredient_id',
        'expected_qty',
        'counted_qty',
        'variance_qty',
        'reason_code',
    ];

    protected $casts = [
        'expected_qty' => 'decimal:3',
        'counted_qty' => 'decimal:3',
        'variance_qty' => 'decimal:3',
    ];

    /**
     * @return BelongsTo<RestaurantInventoryCount, $this>
     */
    public function count(): BelongsTo
    {
        return $this->belongsTo(RestaurantInventoryCount::class, 'count_id');
    }

    /**
     * @return BelongsTo<RestaurantIngredient, $this>
     */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(RestaurantIngredient::class, 'ingredient_id');
    }
}
