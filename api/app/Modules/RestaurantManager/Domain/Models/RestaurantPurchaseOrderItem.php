<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\RestaurantPurchaseOrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne d'un bon de commande fournisseur (RESTO-206, issue #6171).
 *
 * `unit_price_minor` est le prix unitaire négocié et `line_total_minor` le
 * montant de la ligne (minor units) ; `quantity` est en unité de l'ingrédient.
 */
class RestaurantPurchaseOrderItem extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<RestaurantPurchaseOrderItemFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'purchase_order_id',
        'ingredient_id',
        'quantity',
        'unit_price_minor',
        'line_total_minor',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price_minor' => 'integer',
        'line_total_minor' => 'integer',
    ];

    /**
     * @return BelongsTo<RestaurantPurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(RestaurantPurchaseOrder::class, 'purchase_order_id');
    }

    /**
     * @return BelongsTo<RestaurantIngredient, $this>
     */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(RestaurantIngredient::class, 'ingredient_id');
    }
}
