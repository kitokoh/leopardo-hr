<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ligne de commande de vente Retail (BC-17 RETAIL, #7674).
 *
 * `product_name` et `unit_price_minor` sont des SNAPSHOTS pris au moment de
 * la vente (le ticket reste exact meme si le produit change ensuite).
 * `line_total_minor` = round(quantity * unit_price_minor). Unique par
 * (tenant, commande, produit, ligne). Creee UNIQUEMENT via RetailPosService.
 *
 * @property int $id
 * @property string $company_id
 * @property int $order_id
 * @property int $product_id
 * @property string $product_name
 * @property numeric-string $quantity
 * @property int $unit_price_minor
 * @property int $line_total_minor
 * @property int $line_index
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class RetailOrderItem extends Model
{
    use BelongsToCompany;

    protected $table = 'retail_order_items';

    protected $fillable = [
        'company_id',
        'order_id',
        'product_id',
        'product_name',
        'quantity',
        'unit_price_minor',
        'line_total_minor',
        'line_index',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price_minor' => 'integer',
            'line_total_minor' => 'integer',
            'line_index' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<RetailOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(RetailOrder::class, 'order_id');
    }

    /**
     * @return BelongsTo<RetailProduct, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(RetailProduct::class, 'product_id');
    }
}
