<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Facturation d'un frais scolaire à un élève — Issue #5832 (EDU-016).
 *
 * Une charge naît en `pending` et progresse vers `partial`/`paid` au fil des
 * encaissements (EduFeeService), ou vers `waived`/`cancelled` (abandon /
 * annulation). `external_id` unique PAR TENANT → rejeu idempotent. Le
 * montant est figé à la création (copie du tarif) pour une traçabilité
 * comptable stable.
 *
 * @property int $id
 * @property string $company_id
 * @property int $student_id
 * @property int $fee_type_id
 * @property int $academic_year_id
 * @property string $amount
 * @property string $currency
 * @property string $status
 * @property Carbon|null $due_date
 * @property string|null $external_id
 * @property int|null $charged_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class EduFeeCharge extends Model
{
    use BelongsToCompany;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_PAID = 'paid';

    public const STATUS_WAIVED = 'waived';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PARTIAL,
        self::STATUS_PAID,
        self::STATUS_WAIVED,
        self::STATUS_CANCELLED,
    ];

    protected $table = 'edu_fee_charges';

    protected $fillable = [
        'company_id',
        'student_id',
        'fee_type_id',
        'academic_year_id',
        'amount',
        'currency',
        'status',
        'due_date',
        'external_id',
        'charged_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'status' => 'string',
    ];

    /** @return BelongsTo<EduStudent, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(EduStudent::class, 'student_id');
    }

    /** @return BelongsTo<EduFeeType, $this> */
    public function feeType(): BelongsTo
    {
        return $this->belongsTo(EduFeeType::class, 'fee_type_id');
    }

    /** @return BelongsTo<EduAcademicYear, $this> */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(EduAcademicYear::class, 'academic_year_id');
    }

    /** @return HasMany<EduFeePayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(EduFeePayment::class, 'fee_charge_id');
    }

    /**
     * Statuts terminaux : plus aucun encaissement ni transition possible.
     */
    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_PAID, self::STATUS_WAIVED, self::STATUS_CANCELLED], true);
    }
}
