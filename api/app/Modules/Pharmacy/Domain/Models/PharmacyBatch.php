<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Lot physique d'un produit d'officine — PHARMA-003 (#7800).
 *
 * `quantity` est la quantité RESTANTE du lot : elle n'évolue JAMAIS sans un
 * mouvement correspondant dans `pharmacy_stock_movements` (passer par
 * {@see \App\Modules\Pharmacy\Application\Services\PharmacyStockService}).
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $product_id
 * @property string $batch_number
 * @property Carbon $expiry_date
 * @property int $quantity
 * @property string $unit_cost
 * @property int|null $supplier_id
 * @property Carbon|null $received_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PharmacyProduct|null $product
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class PharmacyBatch extends Model
{
    use BelongsToCompany;

    protected $table = 'pharmacy_batches';

    protected $fillable = [
        'company_id',
        'product_id',
        'batch_number',
        'expiry_date',
        'quantity',
        'unit_cost',
        'supplier_id',
        'received_at',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'expiry_date' => 'date',
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'supplier_id' => 'integer',
        'received_at' => 'datetime',
    ];

    /** @return BelongsTo<PharmacyProduct, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(PharmacyProduct::class, 'product_id');
    }

    public function isExpired(): bool
    {
        return $this->expiry_date->isBefore(Carbon::today());
    }
}
