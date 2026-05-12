<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $company_id
 * @property \Illuminate\Support\Carbon $period_start
 * @property \Illuminate\Support\Carbon $period_end
 * @property string $country_code
 * @property string $status
 * @property float $total_gross
 * @property float $total_deductions
 * @property float $total_net
 * @property float $total_employer_cost
 * @property int $employee_count
 * @property \Illuminate\Support\Carbon|null $calculated_at
 * @property string|null $validated_by
 * @property \Illuminate\Support\Carbon|null $validated_at
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
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

    /** @return HasMany<PaySlip, $this> */
    public function paySlips(): HasMany
    {
        return $this->hasMany(PaySlip::class, 'payroll_run_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'validated_by');
    }

    /** @return HasMany<BankExport, $this> */
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
