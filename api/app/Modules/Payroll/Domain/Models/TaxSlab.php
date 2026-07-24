<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $company_id
 * @property string $country_code
 * @property string $name
 * @property float $min_amount
 * @property float $max_amount
 * @property float $rate
 * @property float $fixed_deduction
 * @property Carbon $effective_from
 * @property Carbon $effective_to
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
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

    /**
     * @param  Builder<static>  $q
     * @return Builder<static>
     */
    public function scopeForCountry(Builder $query, string $countryCode): Builder
    {
        return $query->where('country_code', $countryCode);
    }

    /**
     * Scopes to rows effective at a given point in time (defaults to now()
     * when omitted). Accepting an explicit date is what makes retroactive
     * recalculation possible for audit purposes (PA2-ARCH-004): recalculating
     * a payroll run from a past period must use the tax slabs that were
     * effective *during that period*, not today's slabs.
     *
     * @param  Builder<static>  $q
     * @param  Carbon|\DateTimeInterface|string|null  $asOf
     * @return Builder<static>
     */
    public function scopeEffective(Builder $query, $asOf = null): Builder
    {
        $asOf ??= now();

        return $query->where('effective_from', '<=', $asOf)
            ->where(function (Builder $q) use ($asOf) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $asOf);
            });
    }
}
