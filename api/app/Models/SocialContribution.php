<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $company_id
 * @property string $country_code
 * @property string $name
 * @property string $code
 * @property string $type
 * @property float $rate
 * @property float $cap
 * @property Carbon $effective_from
 * @property Carbon $effective_to
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
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

    /**
     * @param  Builder<static>  $q
     * @return Builder<static>
     */
    public function scopeForCountry(Builder $query, string $countryCode): Builder
    {
        return $query->where('country_code', $countryCode);
    }

    /**
     * @param  Builder<static>  $q
     * @return Builder<static>
     */
    public function scopeEmployee(Builder $query): Builder
    {
        return $query->where('type', 'employee');
    }

    /**
     * @param  Builder<static>  $q
     * @return Builder<static>
     */
    public function scopeEmployer(Builder $query): Builder
    {
        return $query->where('type', 'employer');
    }

    /**
     * @param  Builder<static>  $q
     * @return Builder<static>
     */
    public function scopeEffective(Builder $query): Builder
    {
        return $query->where('effective_from', '<=', now())
            ->where(function (Builder $q) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', now());
            });
    }
}
