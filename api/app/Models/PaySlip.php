<?php

namespace App\Models;

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
 * @property float $gross_salary
 * @property float $total_deductions
 * @property float $net_salary
 * @property float $employer_contributions
 * @property float $total_cost
 * @property float $working_days
 * @property float $actual_days_worked
 * @property float $overtime_hours
 * @property string $status
 * @property string|null $pdf_path
 * @property Carbon|null $sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PaySlip extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'payroll_run_id', 'company_id', 'employee_id', 'contract_id',
        'period_start', 'period_end', 'gross_salary', 'total_deductions',
        'net_salary', 'employer_contributions', 'total_cost',
        'working_days', 'actual_days_worked', 'overtime_hours',
        'status', 'pdf_path', 'sent_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'gross_salary' => 'float',
        'total_deductions' => 'float',
        'net_salary' => 'float',
        'employer_contributions' => 'float',
        'total_cost' => 'float',
        'working_days' => 'float',
        'actual_days_worked' => 'float',
        'overtime_hours' => 'float',
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
     * @param  Builder<static>  $q
     * @return Builder<static>
     */
    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * @param  Builder<static>  $q
     * @return Builder<static>
     */
    public function scopeValidated(Builder $query): Builder
    {
        return $query->where('status', 'validated');
    }
}
