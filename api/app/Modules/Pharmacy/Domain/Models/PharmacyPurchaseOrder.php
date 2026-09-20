<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Commande d'achat d'officine — PHARMA-004 (#7801).
 *
 * Cycle : draft → ordered → partially_received → received | cancelled.
 * Numéro `PO-YYYY-XXXX` séquencé PAR TENANT (unique company_id + number).
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $supplier_id
 * @property string $number
 * @property string $status
 * @property Carbon|null $ordered_at
 * @property Carbon|null $received_at
 * @property Carbon|null $cancelled_at
 * @property string|null $notes
 * @property int|null $created_by_employee_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PharmacySupplier|null $supplier
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PharmacyPurchaseOrderLine> $lines
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class PharmacyPurchaseOrder extends Model
{
    use BelongsToCompany;

    public const STATUSES = ['draft', 'ordered', 'partially_received', 'received', 'cancelled'];

    protected $table = 'pharmacy_purchase_orders';

    protected $fillable = [
        'company_id',
        'supplier_id',
        'number',
        'status',
        'ordered_at',
        'received_at',
        'cancelled_at',
        'notes',
        'created_by_employee_id',
    ];

    protected $casts = [
        'supplier_id' => 'integer',
        'ordered_at' => 'datetime',
        'received_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'created_by_employee_id' => 'integer',
    ];

    /** @return BelongsTo<PharmacySupplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(PharmacySupplier::class, 'supplier_id');
    }

    /** @return HasMany<PharmacyPurchaseOrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PharmacyPurchaseOrderLine::class, 'purchase_order_id');
    }
}
