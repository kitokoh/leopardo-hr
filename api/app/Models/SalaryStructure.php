<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryStructure extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'name', 'base_salary', 'currency', 'country_code',
        'frequency', 'active',
    ];

    protected $casts = [
        'base_salary' => 'float',
        'active' => 'boolean',
    ];

    public function components(): HasMany
    {
        return $this->hasMany(SalaryComponent::class, 'salary_structure_id')->orderBy('order');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeForCountry(Builder $query, string $countryCode): Builder
    {
        return $query->where('country_code', $countryCode);
    }
}
