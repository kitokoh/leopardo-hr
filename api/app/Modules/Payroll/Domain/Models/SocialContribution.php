<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Models;

use App\Traits\BelongsToCompany;
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
 *
 * @mixin Builder<static>
 */
class SocialContribution extends Model
{
    use BelongsToCompany;

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
     * Scopes to rows effective at a given point in time (defaults to now()
     * when omitted). Accepting an explicit date is what makes retroactive
     * recalculation possible for audit purposes (PA2-ARCH-004): recalculating
     * a payroll run from a past period must use the social contribution
     * rates that were effective *during that period*, not today's rates.
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
