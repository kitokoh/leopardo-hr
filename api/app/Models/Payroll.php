<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $company_id
 * @property int|null $employee_id
 * @property int $period_month
 * @property int $period_year
 * @property float $gross_salary
 * @property float $overtime_amount
 * @property array<mixed> $bonuses
 * @property array<mixed> $deductions
 * @property array<mixed> $cotisations
 * @property float $ir_amount
 * @property float $advance_deduction
 * @property float $absence_deduction
 * @property float $penalty_deduction
 * @property float $net_salary
 * @property string|null $pdf_path
 * @property string $status
 * @property string|null $validated_by
 * @property \Illuminate\Support\Carbon|null $validated_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Employee|null $employee
 */
class Payroll extends Model
{
    use BelongsToCompany, HasFactory;

    protected $table = 'payrolls';

    protected $fillable = [
        'company_id', 'employee_id', 'period_month', 'period_year',
        'gross_salary', 'overtime_amount', 'bonuses', 'deductions', 'cotisations',
        'ir_amount', 'advance_deduction', 'absence_deduction', 'penalty_deduction',
        'net_salary', 'pdf_path', 'status', 'validated_by', 'validated_at',
    ];

    protected $casts = [
        'gross_salary' => 'float', 'overtime_amount' => 'float',
        'bonuses' => 'array', 'deductions' => 'array', 'cotisations' => 'array',
        'ir_amount' => 'float', 'advance_deduction' => 'float',
        'absence_deduction' => 'float', 'penalty_deduction' => 'float',
        'net_salary' => 'float', 'validated_at' => 'datetime',
    ];

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function validator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'validated_by');
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<static> $q
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeDraft(Builder $q): Builder
    {
        return $q->where('status', 'draft');
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<static> $q
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeValidated(Builder $q): Builder
    {
        return $q->where('status', 'validated');
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<static> $q
     * @param int $month
     * @param int $year
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForPeriod(Builder $q, int $month, int $year): Builder
    {
        return $q->where('period_month', $month)->where('period_year', $year);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<static> $q
     * @param int $id
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForEmployee(Builder $q, int $id): Builder
    {
        return $q->where('employee_id', $id);
    }
}
