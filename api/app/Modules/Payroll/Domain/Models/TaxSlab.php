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
 * @property float $min_amount
 * @property float $max_amount
 * @property float $rate
 * @property float $fixed_deduction
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property string $status
 * @property int|null $submitted_by
 * @property int|null $validated_by
 * @property Carbon|null $validated_at
 * @property string|null $rejection_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class TaxSlab extends Model
{
    use BelongsToCompany;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_VALIDATION = 'pending_validation';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUPERSEDED = 'superseded';

    protected $fillable = [
        'company_id', 'country_code', 'name', 'min_amount', 'max_amount',
        'rate', 'fixed_deduction', 'effective_from', 'effective_to',
        'status', 'submitted_by', 'validated_by', 'validated_at', 'rejection_reason',
    ];

    protected $casts = [
        'min_amount' => 'float',
        'max_amount' => 'float',
        'rate' => 'float',
        'fixed_deduction' => 'float',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'validated_at' => 'datetime',
    ];

    /**
     * @param  Builder<static>  $query
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
     * @param  Builder<static>  $query
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

    /**
     * Limite aux lignes utilisées par le moteur de paie (issue #1813) :
     * une ligne en attente de validation ou brouillon ne doit JAMAIS
     * entrer dans le calcul des bulletins.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePendingValidation(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING_VALIDATION);
    }
}
