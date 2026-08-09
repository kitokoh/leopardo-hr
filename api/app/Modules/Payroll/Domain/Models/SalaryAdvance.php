<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Shared\Traits\Auditable;
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
 * @property string|null $currency
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
 * @property 'pending'|'manager_approved'|'payment_declared'|'employee_confirmed'|'disputed'|'rejected' $validation_status Fine-grained double-validation state; the `disputed` value was added by the 2026_07_24_000001 migration on top of the original 2026_05_31_000001 enum column, so it must be declared explicitly here (Larastan otherwise infers the type from the original migration's enum definition only).
 * @property Carbon|null $requested_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $manager_approved_at
 * @property string|null $manager_approved_by
 * @property Carbon|null $payment_declared_at
 * @property string|null $payment_declared_by
 * @property string|null $payment_reference
 * @property string|null $payment_note
 * @property Carbon|null $employee_confirmed_at
 * @property string|null $proof_path
 * @property string|null $dispute_reason
 * @property Carbon|null $disputed_at
 * @property Carbon|null $dispute_resolved_at
 * @property int|null $dispute_resolved_by
 * @property string|null $dispute_resolution_note
 *
 * @mixin Builder<static>
 */
class SalaryAdvance extends Model
{
    // PA2-PAY-001 — every create/manager-approve/mark-paid/employee-confirm
    // transition writes a company-scoped audit_logs row (old/new dirty
    // attributes + acting employee, resolved from the current request)
    // via the shared Auditable trait, matching the ticket's explicit
    // "audit" acceptance criterion for the double-validation workflow.
    use Auditable, BelongsToCompany, HasFactory;

    protected $table = 'salary_advances';

    protected $fillable = [
        'company_id', 'employee_id', 'amount', 'currency', 'reason', 'status',
        'approved_by', 'decision_comment', 'repayment_months',
        'monthly_deduction', 'amount_remaining', 'repayment_plan',
        // Plan 60 — double validation
        'manager_approved_at', 'manager_approved_by',
        'payment_declared_at', 'payment_declared_by',
        'payment_reference', 'payment_note',
        'employee_confirmed_at', 'validation_status',
        // PA2-PAY-015 — employee dispute
        'dispute_reason', 'disputed_at',
        'dispute_resolved_at', 'dispute_resolved_by', 'dispute_resolution_note',
        // PA2-MOB-006 — optional supporting document (justification, quote,
        // invoice, etc.) attached at request time.
        'proof_path',
    ];

    protected $casts = [
        'amount' => 'float', 'monthly_deduction' => 'float',
        'amount_remaining' => 'float', 'repayment_plan' => 'array',
        // Plan 60
        'manager_approved_at' => 'datetime',
        'payment_declared_at' => 'datetime',
        'employee_confirmed_at' => 'datetime',
        // PA2-PAY-015
        'disputed_at' => 'datetime',
        'dispute_resolved_at' => 'datetime',
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

    /** @return BelongsTo<Employee, $this> */
    public function disputeResolver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'dispute_resolved_by');
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
