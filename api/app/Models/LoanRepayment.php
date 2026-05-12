<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $employee_loan_id
 * @property int|null $company_id
 * @property \Illuminate\Support\Carbon $due_date
 * @property float $amount
 * @property float $principal
 * @property float $interest
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property int|null $payroll_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class LoanRepayment extends Model
{
    protected $table = 'loan_repayments';

    protected $fillable = [
        'employee_loan_id',
        'company_id',
        'due_date',
        'amount',
        'principal',
        'interest',
        'status',
        'paid_at',
        'payroll_id',
    ];

    protected $casts = [
        'due_date' => 'date',
        'amount' => 'float',
        'principal' => 'float',
        'interest' => 'float',
        'paid_at' => 'datetime',
    ];

    /** @return BelongsTo<EmployeeLoan, $this> */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(EmployeeLoan::class, 'employee_loan_id');
    }
}
