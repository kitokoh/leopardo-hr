<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Modules\RestaurantManager\Domain\Enums\OrderItemStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\RestaurantOrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne de commande (RESTO-208, issue #6173).
 *
 * `quantity` est en décimale (demi-portion possible) ; les montants sont en
 * minor units. L'unicité (tenant, commande, produit, ligne) autorise la même
 * ligne produit répétée avec un `line_index` différent.
 */
class RestaurantOrderItem extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<RestaurantOrderItemFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'order_id',
        'product_id',
        'menu_id',
        'quantity',
        'unit_price_minor',
        'line_total_minor',
        'tax_rate_id',
        'tax_minor',
        'status',
        'line_index',
    ];

    protected $attributes = [
        'status' => 'active',
        'line_index' => 0,
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price_minor' => 'integer',
        'line_total_minor' => 'integer',
        'tax_minor' => 'integer',
        'status' => OrderItemStatus::class,
        'line_index' => 'integer',
    ];

    /**
     * @return BelongsTo<RestaurantOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(RestaurantOrder::class, 'order_id');
    }

    /**
     * @return BelongsTo<RestaurantProduct, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(RestaurantProduct::class, 'product_id');
    }

    /**
     * @return BelongsTo<RestaurantMenu, $this>
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(RestaurantMenu::class, 'menu_id');
    }
}
