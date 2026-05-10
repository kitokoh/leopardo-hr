<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'period_start', 'period_end', 'country_code', 'status',
        'total_gross', 'total_deductions', 'total_net', 'total_employer_cost',
        'employee_count', 'calculated_at', 'validated_by', 'validated_at',
        'paid_at', 'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'total_gross' => 'float',
        'total_deductions' => 'float',
        'total_net' => 'float',
        'total_employer_cost' => 'float',
        'employee_count' => 'integer',
        'calculated_at' => 'datetime',
        'validated_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function paySlips(): HasMany
    {
        return $this->hasMany(PaySlip::class, 'payroll_run_id');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'validated_by');
    }

    public function bankExports(): HasMany
    {
        return $this->hasMany(BankExport::class, 'payroll_run_id');
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    public function scopeCalculated(Builder $query): Builder
    {
        return $query->where('status', 'calculated');
    }

    public function scopeValidated(Builder $query): Builder
    {
        return $query->where('status', 'validated');
    }
}
