<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ligne de vente comptoir — PHARMA-005 (#7802). Prix unitaire et taux de
 * taxe FIGÉS au moment de la vente (jamais recalculés depuis le produit).
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $sale_id
 * @property int $product_id
 * @property int $quantity
 * @property string $unit_price
 * @property string $tax_rate
 * @property string $line_total
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PharmacySale|null $sale
 * @property-read PharmacyProduct|null $product
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class PharmacySaleLine extends Model
{
    use BelongsToCompany;

    protected $table = 'pharmacy_sale_lines';

    protected $fillable = [
        'company_id',
        'sale_id',
        'product_id',
        'quantity',
        'unit_price',
        'tax_rate',
        'line_total',
    ];

    protected $casts = [
        'sale_id' => 'integer',
        'product_id' => 'integer',
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    /** @return BelongsTo<PharmacySale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(PharmacySale::class, 'sale_id');
    }

    /** @return BelongsTo<PharmacyProduct, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(PharmacyProduct::class, 'product_id');
    }
}
