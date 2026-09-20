<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ligne de commande d'achat — PHARMA-004 (#7801).
 *
 * `quantity_received` ne dépasse JAMAIS `quantity_ordered` (garde
 * applicative + CHECK SQL).
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $purchase_order_id
 * @property int $product_id
 * @property int $quantity_ordered
 * @property int $quantity_received
 * @property string $unit_price
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PharmacyPurchaseOrder|null $purchaseOrder
 * @property-read PharmacyProduct|null $product
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class PharmacyPurchaseOrderLine extends Model
{
    use BelongsToCompany;

    protected $table = 'pharmacy_purchase_order_lines';

    protected $fillable = [
        'company_id',
        'purchase_order_id',
        'product_id',
        'quantity_ordered',
        'quantity_received',
        'unit_price',
    ];

    protected $casts = [
        'purchase_order_id' => 'integer',
        'product_id' => 'integer',
        'quantity_ordered' => 'integer',
        'quantity_received' => 'integer',
        'unit_price' => 'decimal:2',
    ];

    /** @return BelongsTo<PharmacyPurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PharmacyPurchaseOrder::class, 'purchase_order_id');
    }

    /** @return BelongsTo<PharmacyProduct, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(PharmacyProduct::class, 'product_id');
    }
}
