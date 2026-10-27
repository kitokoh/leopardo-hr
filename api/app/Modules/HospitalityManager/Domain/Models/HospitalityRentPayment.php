<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Échéance de loyer d'un bail — HOSP-005 (#7947, BC-32).
 *
 * Machine à états :
 *   due     → partial | paid | late
 *   partial → partial | paid | late
 *   late    → partial | paid
 *   paid    → état terminal.
 *
 * Le statut `late` est DÉRIVÉ (échéance dépassée et non soldée) : il est
 * recalculé par la commande `hospitality:mark-late-rent-payments`, jamais
 * saisi à la main — d'où la transition `late → partial` quand un encaissement
 * partiel arrive enfin.
 *
 * @property int $id
 * @property string $company_id
 * @property int $lease_id
 * @property int $property_id
 * @property int $unit_id
 * @property string $period
 * @property Carbon $due_on
 * @property int $amount_minor
 * @property int $paid_amount_minor
 * @property string $currency
 * @property string $status
 * @property Carbon|null $paid_at
 * @property string|null $payment_method
 * @property string|null $reference
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class HospitalityRentPayment extends Model
{
    use BelongsToCompany;

    public const STATUS_DUE = 'due';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_PAID = 'paid';

    public const STATUS_LATE = 'late';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_DUE,
        self::STATUS_PARTIAL,
        self::STATUS_PAID,
        self::STATUS_LATE,
    ];

    /**
     * Statuts encore ouverts à un encaissement.
     *
     * @var list<string>
     */
    public const PAYABLE_STATUSES = [
        self::STATUS_DUE,
        self::STATUS_PARTIAL,
        self::STATUS_LATE,
    ];

    protected $table = 'hospitality_rent_payments';

    protected $fillable = [
        'company_id',
        'lease_id',
        'property_id',
        'unit_id',
        'period',
        'due_on',
        'amount_minor',
        'paid_amount_minor',
        'currency',
        'status',
        'paid_at',
        'payment_method',
        'reference',
        'notes',
    ];

    protected $casts = [
        'due_on' => 'date',
        'paid_at' => 'datetime',
        'amount_minor' => 'integer',
        'paid_amount_minor' => 'integer',
    ];

    /**
     * @return BelongsTo<HospitalityLease, $this>
     */
    public function lease(): BelongsTo
    {
        return $this->belongsTo(HospitalityLease::class, 'lease_id');
    }

    /** Reste-t-il quelque chose à encaisser ? */
    public function outstandingMinor(): int
    {
        return max(0, $this->amount_minor - $this->paid_amount_minor);
    }

    public function isSettled(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /** Un encaissement est-il encore possible ? */
    public function isPayable(): bool
    {
        return in_array($this->status, self::PAYABLE_STATUSES, true);
    }
}
