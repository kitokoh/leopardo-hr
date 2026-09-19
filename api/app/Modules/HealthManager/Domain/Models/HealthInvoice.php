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
 * Facture de soins — HC-007 (#7791, BC-30).
 *
 * Patient, lignes d'actes (prix FIGÉS à la facturation), remise, total,
 * paiements. Le total est TOUJOURS recalculé côté serveur :
 * `total = Σ line_total − discount` (critère d'acceptation HC-007).
 *
 * Cycle de vie (`TRANSITIONS`, jamais mass-assigné) :
 *   draft → issued | cancelled
 *   issued → paid | partially_paid | cancelled
 *   partially_paid → paid | cancelled
 *   paid, cancelled : TERMINAUX.
 *
 * Une facture ÉMISE n'est plus modifiable (annulation seulement) ; les
 * statuts de paiement sont DÉRIVÉS du solde exact (jamais posés par le
 * client) ; le sur-paiement est refusé (422). Le numéro `HINV-YYYY-NNNN`
 * (séquence par tenant et par année) est posé à l'émission.
 *
 * @property int $id
 * @property string $company_id
 * @property int $patient_id
 * @property string|null $number
 * @property string $subtotal
 * @property string $discount
 * @property string $total
 * @property string $status
 * @property string|null $notes
 * @property Carbon|null $issued_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, HealthInvoiceItem> $items
 * @property-read \Illuminate\Database\Eloquent\Collection<int, HealthInvoicePayment> $payments
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

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ISSUED,
        self::STATUS_PAID,
        self::STATUS_PARTIALLY_PAID,
        self::STATUS_CANCELLED,
    ];

    /**
     * Statuts qui acceptent un paiement (solde ouvert).
     */
    public const PAYABLE_STATUSES = [
        self::STATUS_ISSUED,
        self::STATUS_PARTIALLY_PAID,
    ];

    /**
     * Statuts qui comptent dans les IMPAYÉS (émis, solde non couvert).
     */
    public const OUTSTANDING_STATUSES = [
        self::STATUS_ISSUED,
        self::STATUS_PARTIALLY_PAID,
    ];

    /**
     * Machine à états stricte (HC-004 pattern) : toute autre transition →
     * 422 HEALTH_INVALID_STATUS_TRANSITION. `paid` et `cancelled` sont
     * TERMINAUX (une facture payée ne s'annule pas via l'API).
     *
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_ISSUED, self::STATUS_CANCELLED],
        self::STATUS_ISSUED => [self::STATUS_PAID, self::STATUS_PARTIALLY_PAID, self::STATUS_CANCELLED],
        self::STATUS_PARTIALLY_PAID => [self::STATUS_PAID, self::STATUS_CANCELLED],
        self::STATUS_PAID => [],
        self::STATUS_CANCELLED => [],
    ];

    protected $table = 'health_invoices';

    /**
     * `number`, `status`, `subtotal`, `total` et les horodatages de cycle
     * de vie sont posés CÔTÉ SERVEUR (jamais mass-assignés) ; `company_id`
     * posé par BelongsToCompany (pattern strict #7712).
     */
    protected $fillable = [
        'patient_id',
        'discount',
        'notes',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'issued_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canTransitionTo(string $target): bool
    {
        return in_array($target, self::TRANSITIONS[$this->status] ?? [], true);
    }

    /**
     * Total déjà encaissé, en CENTIMES (comparaisons exactes, jamais de
     * flottants qui dérivent).
     */
    public function paidCents(): int
    {
        return (int) $this->payments->reduce(
            fn (int $carry, HealthInvoicePayment $payment): int => $carry + (int) round(((float) $payment->amount) * 100),
            0
        );
    }

    public function totalCents(): int
    {
        return (int) round(((float) $this->total) * 100);
    }

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
