<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $company_id
 * @property int|null $employee_id
 * @property float $amount
 * @property string|null $reason
 * @property string $status
 * @property string|null $approved_by
 * @property string|null $decision_comment
 * @property string|null $repayment_months
 * @property float $monthly_deduction
 * @property float $amount_remaining
 * @property array<mixed> $repayment_plan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SalaryAdvance extends Model
{
    use BelongsToCompany, HasFactory;

    protected $table = 'salary_advances';

    protected $fillable = [
        'company_id', 'employee_id', 'amount', 'reason', 'status',
        'approved_by', 'decision_comment', 'repayment_months',
        'monthly_deduction', 'amount_remaining', 'repayment_plan',
        // Plan 60 — double validation
        'manager_approved_at', 'manager_approved_by',
        'payment_declared_at', 'payment_declared_by',
        'payment_reference', 'payment_note',
        'employee_confirmed_at', 'validation_status',
    ];

    protected $casts = [
        'amount' => 'float', 'monthly_deduction' => 'float',
        'amount_remaining' => 'float', 'repayment_plan' => 'array',
        // Plan 60
        'manager_approved_at' => 'datetime',
        'payment_declared_at' => 'datetime',
        'employee_confirmed_at' => 'datetime',
    ];

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    /**
     * @param  Builder<static>  $q
     * @return Builder<static>
     */
    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', 'pending');
    }

    /**
     * @param  Builder<static>  $q
     * @return Builder<static>
     */
    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'active');
    }

    /**
     * @param  Builder<static>  $q
     * @return Builder<static>
     */
    public function scopeForEmployee(Builder $q, int $id): Builder
    {
        return $q->where('employee_id', $id);
    }
}
