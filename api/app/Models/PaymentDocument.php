<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $company_id
 * @property int|null $employee_id
 * @property int|null $payroll_run_id
 * @property int|null $pay_slip_id
 * @property int|null $salary_advance_id
 * @property string $document_type
 * @property string $status
 * @property string $disk
 * @property string|null $path
 * @property string|null $filename
 * @property string $mime_type
 * @property int|null $size_bytes
 * @property string|null $error_message
 * @property array<mixed>|null $metadata
 * @property int|null $requested_by
 * @property Carbon|null $generated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PaymentDocument extends Model
{
    use BelongsToCompany;

    public const TYPE_PAYMENT_RECEIPT = 'payment_receipt';

    public const TYPE_PAYMENT_SLIP = 'payment_slip';

    public const TYPE_ADVANCE_RECEIPT = 'advance_receipt';

    public const TYPE_PAYROLL_SUMMARY = 'payroll_summary';

    public const STATUS_PENDING = 'pending';

    public const STATUS_GENERATING = 'generating';

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_FAILED = 'failed';

    /** @var list<string> */
    public const TYPES = [
        self::TYPE_PAYMENT_RECEIPT,
        self::TYPE_PAYMENT_SLIP,
        self::TYPE_ADVANCE_RECEIPT,
        self::TYPE_PAYROLL_SUMMARY,
    ];

    protected $fillable = [
        'company_id',
        'employee_id',
        'payroll_run_id',
        'pay_slip_id',
        'salary_advance_id',
        'document_type',
        'status',
        'disk',
        'path',
        'filename',
        'mime_type',
        'size_bytes',
        'error_message',
        'metadata',
        'requested_by',
        'generated_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'generated_at' => 'datetime',
        'size_bytes' => 'integer',
    ];

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requested_by');
    }

    /** @return BelongsTo<PayrollRun, $this> */
    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    /** @return BelongsTo<PaySlip, $this> */
    public function paySlip(): BelongsTo
    {
        return $this->belongsTo(PaySlip::class, 'pay_slip_id');
    }

    /** @return BelongsTo<SalaryAdvance, $this> */
    public function salaryAdvance(): BelongsTo
    {
        return $this->belongsTo(SalaryAdvance::class, 'salary_advance_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeAvailableToEmployee(Builder $query, Employee $employee): Builder
    {
        return $query
            ->where('company_id', $employee->company_id)
            ->where('employee_id', $employee->id);
    }
}
