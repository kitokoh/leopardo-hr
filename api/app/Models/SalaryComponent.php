<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
