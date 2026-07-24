<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * PA2-PAY-007 — Immutable financial journal entry for an employee.
 *
 * Every salary advance, payment, or manual balance adjustment is recorded
 * as a LedgerEntry so the full financial history of an employee (and its
 * running balance) is always reconstructable and auditable.
 *
 * @property int $id
 * @property string $company_id
 * @property int $employee_id
 * @property string $entry_type
 * @property float $amount
 * @property string $currency
 * @property float $balance_after
 * @property string|null $description
 * @property string|null $source_type
 * @property int|null $source_id
 * @property int|null $payment_document_id
 * @property int|null $created_by
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 *
 * @mixin Builder<static>
 */
class LedgerEntry extends Model
{
    use BelongsToCompany;

    public $timestamps = false;

    protected $table = 'ledger_entries';

    public const TYPE_ADVANCE = 'advance';

    public const TYPE_PAYMENT = 'payment';

    public const TYPE_ADJUSTMENT = 'adjustment';

    /** @var list<string> */
    public const TYPES = [
        self::TYPE_ADVANCE,
        self::TYPE_PAYMENT,
        self::TYPE_ADJUSTMENT,
    ];

    protected $fillable = [
        'company_id',
        'employee_id',
        'entry_type',
        'amount',
        'currency',
        'balance_after',
        'description',
        'source_type',
        'source_id',
        'payment_document_id',
        'created_by',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'balance_after' => 'float',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    /** @return BelongsTo<PaymentDocument, $this> */
    public function paymentDocument(): BelongsTo
    {
        return $this->belongsTo(PaymentDocument::class, 'payment_document_id');
    }

    /**
     * @param  Builder<static>  $q
     * @return Builder<static>
     */
    public function scopeForEmployee(Builder $q, int $employeeId): Builder
    {
        return $q->where('employee_id', $employeeId);
    }

    /**
     * @param  Builder<static>  $q
     * @return Builder<static>
     */
    public function scopeOfType(Builder $q, string $type): Builder
    {
        return $q->where('entry_type', $type);
    }

    /**
     * @param  Builder<static>  $q
     * @return Builder<static>
     */
    public function scopeNewestFirst(Builder $q): Builder
    {
        return $q->orderByDesc('created_at')->orderByDesc('id');
    }
}
