<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Niveau de stock courant d'un produit dans un emplacement (BC-17 RETAIL, #7673).
 *
 * Un seul niveau par (tenant, emplacement, produit) ; `reorder_level` et
 * `alert_threshold` declenchent les alertes de reapprovisionnement.
 * Les quantites ne sont ecrites QUE via RetailStockService (transaction +
 * verrou de ligne — pattern RestaurantStockLevel #6170).
 *
 * @property int $id
 * @property string $company_id
 * @property int $location_id
 * @property int $product_id
 * @property numeric-string $quantity
 * @property int|null $avg_cost_minor
 * @property numeric-string|null $reorder_level
 * @property numeric-string|null $alert_threshold
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class RetailStockLevel extends Model
{
    use BelongsToCompany;

    protected $table = 'retail_stock_levels';

    protected $fillable = [
        'company_id',
        'location_id',
        'product_id',
        'quantity',
        'avg_cost_minor',
        'reorder_level',
        'alert_threshold',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'avg_cost_minor' => 'integer',
            'reorder_level' => 'decimal:3',
            'alert_threshold' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<RetailLocation, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(RetailLocation::class, 'location_id');
    }

    /**
     * @return BelongsTo<RetailProduct, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(RetailProduct::class, 'product_id');
    }

    /**
     * @return HasMany<RetailInventoryMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(RetailInventoryMovement::class, 'stock_level_id');
    }
}
