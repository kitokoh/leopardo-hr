<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $company_id
 * @property int $payment_batch_id
 * @property int $employee_id
 * @property int|null $pay_slip_id
 * @property int|null $salary_advance_id
 * @property float $amount
 * @property string $currency
 * @property string $status
 * @property Carbon|null $paid_at
 * @property Carbon|null $confirmed_at
 * @property array<string, mixed>|null $metadata
 * @property-read PaymentBatch $batch
 */
class PaymentItem extends Model
{
    use BelongsToCompany;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_CONFIRMED = 'confirmed';

    protected $fillable = [
        'company_id',
        'payment_batch_id',
        'employee_id',
        'pay_slip_id',
        'salary_advance_id',
        'amount',
        'currency',
        'status',
        'paid_at',
        'confirmed_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'float',
        'paid_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<PaymentBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(PaymentBatch::class, 'payment_batch_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /** @return BelongsTo<PaySlip, $this> */
    public function paySlip(): BelongsTo
    {
        return $this->belongsTo(PaySlip::class, 'pay_slip_id');
    }
}
