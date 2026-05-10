<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
