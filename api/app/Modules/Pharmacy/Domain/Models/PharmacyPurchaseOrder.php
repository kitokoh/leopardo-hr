<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Commande d'achat d'officine — PHARMA-004 (#7801).
 *
 * Numéro `PO-YYYY-XXXX` séquencé PAR TENANT (unique (company_id, number),
 * attribué en transaction par PharmacyPurchasingService). Cycle d'état :
 * draft → ordered → partially_received → received | cancelled — les
 * transitions invalides sont refusées par le service (réception d'un
 * draft, annulation d'un received…). La réception délègue à
 * PharmacyStockService::receive() : lots + mouvements `receipt` (#7800).
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $supplier_id
 * @property string $number
 * @property string $status
 * @property Carbon|null $ordered_at
 * @property Carbon|null $received_at
 * @property string|null $notes
 * @property int|null $created_by_employee_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class PharmacyPurchaseOrder extends Model
{
    use BelongsToCompany;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ORDERED = 'ordered';

    public const STATUS_PARTIALLY_RECEIVED = 'partially_received';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ORDERED,
        self::STATUS_PARTIALLY_RECEIVED,
        self::STATUS_RECEIVED,
        self::STATUS_CANCELLED,
    ];

    protected $table = 'pharmacy_purchase_orders';

    protected $fillable = [
        'company_id',
        'supplier_id',
        'number',
        'status',
        'ordered_at',
        'received_at',
        'notes',
        'created_by_employee_id',
    ];

    protected $casts = [
        'supplier_id' => 'integer',
        'ordered_at' => 'datetime',
        'received_at' => 'datetime',
        'created_by_employee_id' => 'integer',
    ];

    /**
     * @return BelongsTo<PharmacySupplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(PharmacySupplier::class, 'supplier_id');
    }

    /**
     * @return HasMany<PharmacyPurchaseOrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PharmacyPurchaseOrderLine::class, 'purchase_order_id');
    }
}
