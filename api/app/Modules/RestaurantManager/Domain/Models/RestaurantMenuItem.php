<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\RestaurantMenuItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne de menu : produit rattaché à un menu avec sa position (RESTO-204, issue #6169).
 *
 * Un produit ne peut apparaître qu'une fois par menu
 * (UNIQUE company_id, menu_id, product_id) ; `is_optional` marque les
 * suggestions/options à la carte.
 */
class RestaurantMenuItem extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<RestaurantMenuItemFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'menu_id',
        'product_id',
        'position',
        'is_optional',
    ];

    protected $attributes = [
        'position' => 0,
        'is_optional' => false,
    ];

    protected $casts = [
        'position' => 'integer',
        'is_optional' => 'boolean',
    ];

    /**
     * @return BelongsTo<RestaurantMenu, $this>
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(RestaurantMenu::class, 'menu_id');
    }

    /**
     * @return BelongsTo<RestaurantProduct, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(RestaurantProduct::class, 'product_id');
    }
}
