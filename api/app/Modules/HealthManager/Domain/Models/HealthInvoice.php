<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Facture de soins — Issue #7791 (BC-31).
 *
 * Numéro `HINV-YYYY-NNNN` séquentiel par tenant/année, généré serveur.
 * Invariants (spec §4, service) : total recalculé serveur (Σ line_total −
 * discount ≥ 0) ; brouillon modifiable, émise non modifiable (annulation
 * seulement) ; cumul paiements ≤ total ; jamais supprimée physiquement
 * une fois émise. Statut borné (CHECK en base).
 *
 * @property int $id
 * @property string $company_id
 * @property string $number
 * @property int $patient_id
 * @property string $status
 * @property string $currency
 * @property string $subtotal
 * @property string $discount
 * @property string $total
 * @property string $amount_paid
 * @property Carbon|null $issued_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class HealthInvoice extends Model
{
    use BelongsToCompany;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_PAID = 'paid';

    public const STATUS_PARTIALLY_PAID = 'partially_paid';

    public const STATUS_CANCELLED = 'cancelled';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ISSUED,
        self::STATUS_PAID,
        self::STATUS_PARTIALLY_PAID,
        self::STATUS_CANCELLED,
    ];

    protected $table = 'health_invoices';

    protected $fillable = [
        'company_id',
        'number',
        'patient_id',
        'status',
        'currency',
        'subtotal',
        'discount',
        'total',
        'amount_paid',
        'issued_at',
    ];

    protected $casts = [
        'patient_id' => 'integer',
        'status' => 'string',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'issued_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<HealthPatient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(HealthPatient::class, 'patient_id');
    }

    /**
     * @return HasMany<HealthInvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(HealthInvoiceItem::class, 'invoice_id');
    }

    /**
     * @return HasMany<HealthInvoicePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(HealthInvoicePayment::class, 'invoice_id');
    }
}
