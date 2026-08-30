<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Frais scolaire — Issue #5832 (EDU-016).
 *
 * Tenant-scoped. Contrat Accounting : EduManager ne crée aucune écriture
 * comptable — `EduFee` est le read model que Accounting consomme via son
 * propre flux. `external_reference` unique par tenant (rejeu idempotent).
 *
 * @property int $id
 * @property string $company_id
 * @property int $student_id
 * @property int|null $admission_id
 * @property string $label
 * @property string $amount
 * @property Carbon $due_date
 * @property string $status
 * @property string|null $external_reference
 * @property string|null $payment_reference
 * @property Carbon|null $paid_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class EduFee extends Model
{
    use BelongsToCompany;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_WAIVED = 'waived';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PAID,
        self::STATUS_WAIVED,
        self::STATUS_CANCELLED,
    ];

    public const TERMINAL_STATUSES = [
        self::STATUS_PAID,
        self::STATUS_WAIVED,
        self::STATUS_CANCELLED,
    ];

    protected $table = 'edu_fees';

    protected $fillable = [
        'company_id',
        'student_id',
        'admission_id',
        'label',
        'amount',
        'due_date',
        'status',
        'external_reference',
        'payment_reference',
        'paid_at',
        'created_by',
    ];

    protected $casts = [
        'student_id' => 'integer',
        'admission_id' => 'integer',
        'amount' => 'string',
        'due_date' => 'date',
        'status' => 'string',
        'paid_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<EduStudent, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(EduStudent::class, 'student_id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }
}
