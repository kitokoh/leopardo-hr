<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Rendez-vous patient ↔ praticien — HC-004 (#7788, BC-30).
 *
 * La réception planifie, le praticien consulte SON agenda. Cycle de vie
 * borné par la machine à états TRANSITIONS (422 sur transition invalide) :
 *
 *   scheduled → confirmed | checked_in | cancelled | no_show
 *   confirmed → checked_in | cancelled | no_show
 *   checked_in → completed | cancelled
 *   completed / cancelled / no_show → terminaux
 *
 * Le NON-chevauchement de créneau par praticien (statuts actifs
 * scheduled|confirmed|checked_in) est garanti côté contrôleur dans une
 * transaction lockForUpdate → 409 HEALTH_APPOINTMENT_CONFLICT.
 *
 * @property int $id
 * @property string $company_id
 * @property int $patient_id
 * @property int $practitioner_id
 * @property int|null $department_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string $reason
 * @property string $status
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class HealthAppointment extends Model
{
    use BelongsToCompany;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CHECKED_IN = 'checked_in';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_NO_SHOW = 'no_show';

    public const STATUSES = [
        self::STATUS_SCHEDULED,
        self::STATUS_CONFIRMED,
        self::STATUS_CHECKED_IN,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
        self::STATUS_NO_SHOW,
    ];

    /**
     * Statuts occupant RÉELLEMENT le créneau du praticien : un rendez-vous
     * annulé ou non honoré libère le créneau (re-réservable sans 409).
     */
    public const ACTIVE_STATUSES = [
        self::STATUS_SCHEDULED,
        self::STATUS_CONFIRMED,
        self::STATUS_CHECKED_IN,
    ];

    /**
     * Machine à états du cycle de vie (HC-004) — toute transition absente
     * de cette table est refusée (422 HEALTH_INVALID_STATUS_TRANSITION).
     *
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        self::STATUS_SCHEDULED => [self::STATUS_CONFIRMED, self::STATUS_CHECKED_IN, self::STATUS_CANCELLED, self::STATUS_NO_SHOW],
        self::STATUS_CONFIRMED => [self::STATUS_CHECKED_IN, self::STATUS_CANCELLED, self::STATUS_NO_SHOW],
        self::STATUS_CHECKED_IN => [self::STATUS_COMPLETED, self::STATUS_CANCELLED],
        self::STATUS_COMPLETED => [],
        self::STATUS_CANCELLED => [],
        self::STATUS_NO_SHOW => [],
    ];

    protected $table = 'health_appointments';

    /**
     * `status` HORS $fillable : le cycle de vie passe UNIQUEMENT par
     * l'endpoint de transition validé ; `company_id` posé par
     * BelongsToCompany (pattern strict #7712).
     */
    protected $fillable = [
        'patient_id',
        'practitioner_id',
        'department_id',
        'starts_at',
        'ends_at',
        'reason',
        'notes',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    /**
     * @return BelongsTo<HealthPatient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(HealthPatient::class, 'patient_id');
    }

    /**
     * @return BelongsTo<HealthPractitioner, $this>
     */
    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(HealthPractitioner::class, 'practitioner_id');
    }

    /**
     * @return BelongsTo<HealthDepartment, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(HealthDepartment::class, 'department_id');
    }
}
