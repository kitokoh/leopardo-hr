<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $company_id
 * @property string $country_code
 * @property string $name
 * @property float $min_amount
 * @property float $max_amount
 * @property float $rate
 * @property float $fixed_deduction
 * @property \Illuminate\Support\Carbon $effective_from
 * @property \Illuminate\Support\Carbon $effective_to
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class TaxSlab extends Model
{
    protected $fillable = [
        'company_id', 'country_code', 'name', 'min_amount', 'max_amount',
        'rate', 'fixed_deduction', 'effective_from', 'effective_to',
    ];

    protected $casts = [
        'min_amount' => 'float',
        'max_amount' => 'float',
        'rate' => 'float',
        'fixed_deduction' => 'float',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function scopeForCountry(Builder $query, string $countryCode): Builder
    {
        return $query->where('country_code', $countryCode);
    }

    public function scopeEffective(Builder $query): Builder
    {
        return $query->where('effective_from', '<=', now())
            ->where(function (Builder $q) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', now());
            });
    }
}
