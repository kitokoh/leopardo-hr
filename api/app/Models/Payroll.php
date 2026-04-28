<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'validated_by');
    }

    public function scopeDraft(Builder $q): Builder
    {
        return $q->where('status', 'draft');
    }

    public function scopeValidated(Builder $q): Builder
    {
        return $q->where('status', 'validated');
    }

    public function scopeForPeriod(Builder $q, int $month, int $year): Builder
    {
        return $q->where('period_month', $month)->where('period_year', $year);
    }

    public function scopeForEmployee(Builder $q, int $id): Builder
    {
        return $q->where('employee_id', $id);
    }
}
