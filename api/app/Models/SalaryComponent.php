<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $company_id
 * @property int|null $salary_structure_id
 * @property string $name
 * @property string $code
 * @property string $type
 * @property string|null $calculation_type
 * @property float $amount
 * @property float $percentage
 * @property string|null $formula
 * @property bool $is_taxable
 * @property bool $is_recurring
 * @property int $order
 * @property bool $active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class SalaryComponent extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'salary_structure_id', 'name', 'code', 'type',
        'calculation_type', 'amount', 'percentage', 'formula',
        'is_taxable', 'is_recurring', 'order', 'active',
    ];

    protected $casts = [
        'amount' => 'float',
        'percentage' => 'float',
        'is_taxable' => 'boolean',
        'is_recurring' => 'boolean',
        'order' => 'integer',
        'active' => 'boolean',
    ];

    /** @return BelongsTo<SalaryStructure, $this> */
    public function salaryStructure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class, 'salary_structure_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeEarnings(Builder $query): Builder
    {
        return $query->where('type', 'earning');
    }

    public function scopeDeductions(Builder $query): Builder
    {
        return $query->where('type', 'deduction');
    }

    public function scopeEmployerContributions(Builder $query): Builder
    {
        return $query->where('type', 'employer_contribution');
    }
}
