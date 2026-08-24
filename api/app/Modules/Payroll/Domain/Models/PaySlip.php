<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $payroll_run_id
 * @property int|null $company_id
 * @property int|null $employee_id
 * @property int|null $contract_id
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property string|null $rules_version
 * @property Carbon|null $rules_period
 * @property string|null $rules_identifier
 * @property float $gross_salary
 * @property float $total_deductions
 * @property float $net_salary
 * @property float $employer_contributions
 * @property float $total_cost
 * @property float $working_days
 * @property float $actual_days_worked
 * @property float $overtime_hours
 * @property float $paid_leave_days
 * @property float $unpaid_leave_days
 * @property float $public_holiday_days
 * @property bool $has_attendance_data
 * @property string $status
 * @property string|null $pdf_path
 * @property int|null $original_slip_id
 * @property Carbon|null $sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class PaySlip extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'payroll_run_id', 'company_id', 'employee_id', 'contract_id',
        'period_start', 'period_end', 'rules_version', 'rules_period',
        'rules_identifier', 'gross_salary', 'total_deductions',
        'net_salary', 'employer_contributions', 'total_cost',
        'working_days', 'actual_days_worked', 'overtime_hours',
        'paid_leave_days', 'unpaid_leave_days', 'public_holiday_days',
        'has_attendance_data',
        'status', 'pdf_path', 'sent_at',
        'original_slip_id',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'rules_period' => 'date',
        'gross_salary' => 'float',
        'total_deductions' => 'float',
        'net_salary' => 'float',
        'employer_contributions' => 'float',
        'total_cost' => 'float',
        'working_days' => 'float',
        'actual_days_worked' => 'float',
        'overtime_hours' => 'float',
        'paid_leave_days' => 'float',
        'unpaid_leave_days' => 'float',
        'public_holiday_days' => 'float',
        'has_attendance_data' => 'boolean',
        'sent_at' => 'datetime',
    ];

    /** @return BelongsTo<PayrollRun, $this> */
    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /** @return HasMany<PaySlipLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PaySlipLine::class, 'pay_slip_id')->orderBy('order');
    }

    /**
     * Issue #1983 — bulletin ORIGINAL corrigé par ce bulletin de
     * régularisation (null pour un bulletin standard).
     *
     * @return BelongsTo<PaySlip, $this>
     */
    public function originalSlip(): BelongsTo
    {
        return $this->belongsTo(PaySlip::class, 'original_slip_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeValidated(Builder $query): Builder
    {
        return $query->where('status', 'validated');
    }
}
