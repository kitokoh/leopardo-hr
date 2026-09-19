<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Lot de stock d'officine — PHARMA-003 (#7800).
 *
 * Un lot est identifié par (tenant, produit, n° de lot) et porte sa date de
 * péremption et sa quantité RESTANTE. La quantité n'est JAMAIS écrite
 * directement par les contrôleurs : seul `PharmacyStockService` la modifie,
 * en transaction avec verrou pessimiste, et chaque changement journalise un
 * `PharmacyStockMovement` (append-only, traçabilité réglementaire).
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $product_id
 * @property int|null $supplier_id
 * @property string $batch_number
 * @property Carbon $expiry_date
 * @property int $quantity
 * @property string|null $unit_cost
 * @property Carbon|null $received_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class PharmacyBatch extends Model
{
    use BelongsToCompany;

    protected $table = 'pharmacy_batches';

    protected $fillable = [
        'company_id',
        'product_id',
        'supplier_id',
        'batch_number',
        'expiry_date',
        'quantity',
        'unit_cost',
        'received_at',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'supplier_id' => 'integer',
        'expiry_date' => 'date',
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'received_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<PharmacyProduct, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(PharmacyProduct::class, 'product_id');
    }

    /**
     * Le lot est périmé si sa date de péremption est STRICTEMENT antérieure
     * à aujourd'hui (un lot périmant aujourd'hui reste délivrable).
     */
    public function isExpired(): bool
    {
        return $this->expiry_date->lt(Carbon::today());
    }
}
