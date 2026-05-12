<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $company_id
 * @property string $name
 * @property float $base_salary
 * @property string $currency
 * @property string $country_code
 * @property string $frequency
 * @property bool $active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
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

    /** @return HasMany<SalaryComponent, $this> */
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
