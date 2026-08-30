<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Modules\RestaurantManager\Domain\Enums\StockMovementReason;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\RestaurantInventoryMovementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mouvement d'inventaire (entrée/sortie de stock) — RESTO-205, issue #6170.
 *
 * `quantity_delta` est signé (négatif = sortie) ; `reason_code` est un code
 * contrôlé (vente, réception, inventaire, ajustement...). `reference_type`/
 * `reference_id` tracent la source (commande, bon de réception, comptage).
 */
class RestaurantInventoryMovement extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<RestaurantInventoryMovementFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'ingredient_id',
        'stock_level_id',
        'quantity_delta',
        'reason_code',
        'reference_type',
        'reference_id',
        'note_redacted',
        'user_id',
    ];

    protected $casts = [
        'quantity_delta' => 'decimal:3',
        'reason_code' => StockMovementReason::class,
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

    /**
     * @return BelongsTo<RestaurantStockLevel, $this>
     */
    public function stockLevel(): BelongsTo
    {
        return $this->belongsTo(RestaurantStockLevel::class, 'stock_level_id');
    }
}
