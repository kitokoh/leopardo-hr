<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Bail locatif d'une unité — HOSP-005 (#7947, BC-32).
 *
 * Machine à états (transitions gardées — 409 INVALID_LEASE_TRANSITION) :
 *   active → ended (fin de bail arrivée à terme) | terminated (résiliation)
 *   ended / terminated → états terminaux.
 *
 * Un seul bail `active` par unité (index unique PARTIEL en base) : la
 * contrainte disparaît avec l'état, un historique reste possible.
 *
 * @property int $id
 * @property string $company_id
 * @property int $property_id
 * @property int $unit_id
 * @property string $tenant_name
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property int $monthly_rent_minor
 * @property string $currency
 * @property int $billing_day
 * @property int|null $deposit_minor
 * @property string $status
 * @property Carbon|null $terminated_on
 * @property string|null $notes
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class HospitalityLease extends Model
{
    use BelongsToCompany;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ENDED = 'ended';

    public const STATUS_TERMINATED = 'terminated';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_ENDED,
        self::STATUS_TERMINATED,
    ];

    /**
     * Machine à états : statut → transitions autorisées.
     *
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        self::STATUS_ACTIVE => [self::STATUS_ENDED, self::STATUS_TERMINATED],
        self::STATUS_ENDED => [],
        self::STATUS_TERMINATED => [],
    ];

    protected $table = 'hospitality_leases';

    protected $fillable = [
        'company_id',
        'property_id',
        'unit_id',
        'tenant_name',
        'contact_email',
        'contact_phone',
        'starts_on',
        'ends_on',
        'monthly_rent_minor',
        'currency',
        'billing_day',
        'deposit_minor',
        'status',
        'terminated_on',
        'notes',
        'version',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'terminated_on' => 'date',
        'monthly_rent_minor' => 'integer',
        'billing_day' => 'integer',
        'deposit_minor' => 'integer',
        'version' => 'integer',
    ];

    /**
     * @return BelongsTo<HospitalityProperty, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(HospitalityProperty::class, 'property_id');
    }

    /**
     * @return BelongsTo<HospitalityUnit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(HospitalityUnit::class, 'unit_id');
    }

    /**
     * @return HasMany<HospitalityRentPayment, $this>
     */
    public function rentPayments(): HasMany
    {
        return $this->hasMany(HospitalityRentPayment::class, 'lease_id');
    }

    public function canTransitionTo(string $target): bool
    {
        return in_array($target, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_ENDED, self::STATUS_TERMINATED], true);
    }

    /**
     * Le bail couvre-t-il la période `$period` (format YYYY-MM) ?
     *
     * Un bail sans `ends_on` est à durée indéterminée : il couvre toute
     * période postérieure à son début.
     */
    public function coversPeriod(string $period): bool
    {
        $periodStart = Carbon::parse($period.'-01')->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();

        if ($periodEnd->lt($this->starts_on->copy()->startOfDay())) {
            return false;
        }

        return $this->ends_on === null || $periodStart->lte($this->ends_on->copy()->endOfDay());
    }
}
