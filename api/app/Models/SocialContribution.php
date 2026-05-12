<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $company_id
 * @property string $country_code
 * @property string $name
 * @property string $code
 * @property string $type
 * @property float $rate
 * @property float $cap
 * @property \Illuminate\Support\Carbon $effective_from
 * @property \Illuminate\Support\Carbon $effective_to
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class SocialContribution extends Model
{
    protected $fillable = [
        'company_id', 'country_code', 'name', 'code', 'type',
        'rate', 'cap', 'effective_from', 'effective_to',
    ];

    protected $casts = [
        'rate' => 'float',
        'cap' => 'float',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function scopeForCountry(Builder $query, string $countryCode): Builder
    {
        return $query->where('country_code', $countryCode);
    }

    public function scopeEmployee(Builder $query): Builder
    {
        return $query->where('type', 'employee');
    }

    public function scopeEmployer(Builder $query): Builder
    {
        return $query->where('type', 'employer');
    }

    public function scopeEffective(Builder $query): Builder
    {
        return $query->where('effective_from', '<=', now())
            ->where(function (Builder $q) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', now());
            });
    }
}
