<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Hospitalisation (séjour) — HC-006 (#7790, BC-30).
 *
 * Admission d'un patient sur un lit, suivi du séjour, transfert de lit,
 * sortie. Cycle de vie : `admitted` → (`transferred` ×n) → `discharged`
 * (terminal). La cohérence lit ↔ séjour est garantie SOUS TRANSACTION
 * (lockForUpdate sur le lit) ET par index unique partiel en base : un lit
 * ne porte jamais deux séjours actifs, un patient n'a qu'un séjour actif.
 * Transfert tracé (`transferred_from_bed_id`, `transferred_at`) ; la
 * sortie et le transfert LIBÈRENT le lit d'origine.
 *
 * @property int $id
 * @property string $company_id
 * @property int $patient_id
 * @property int $practitioner_id
 * @property int $department_id
 * @property int $bed_id
 * @property int|null $transferred_from_bed_id
 * @property string $reason
 * @property Carbon $admitted_at
 * @property Carbon|null $expected_discharge_at
 * @property Carbon|null $discharged_at
 * @property Carbon|null $transferred_at
 * @property string $status
 * @property string|null $discharge_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class HealthAdmission extends Model
{
    use BelongsToCompany;

    public const STATUS_ADMITTED = 'admitted';

    public const STATUS_TRANSFERRED = 'transferred';

    public const STATUS_DISCHARGED = 'discharged';

    public const STATUSES = [
        self::STATUS_ADMITTED,
        self::STATUS_TRANSFERRED,
        self::STATUS_DISCHARGED,
    ];

    /**
     * Séjours occupant RÉELLEMENT un lit (patient dans les murs).
     */
    public const ACTIVE_STATUSES = [
        self::STATUS_ADMITTED,
        self::STATUS_TRANSFERRED,
    ];

    protected $table = 'health_admissions';

    /**
     * `bed_id`, `status` et la traçabilité de transfert/sortie sont posés
     * CÔTÉ SERVEUR sous transaction (jamais mass-assignés) ; `company_id`
     * posé par BelongsToCompany (pattern strict #7712).
     */
    protected $fillable = [
        'patient_id',
        'practitioner_id',
        'department_id',
        'reason',
        'admitted_at',
        'expected_discharge_at',
    ];

    protected $casts = [
        'admitted_at' => 'datetime',
        'expected_discharge_at' => 'datetime',
        'discharged_at' => 'datetime',
        'transferred_at' => 'datetime',
        // Notes de sortie chiffrées au repos (art. 9 RGPD).
        'discharge_notes' => 'encrypted',
    ];

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
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

    /**
     * @return BelongsTo<HealthBed, $this>
     */
    public function bed(): BelongsTo
    {
        return $this->belongsTo(HealthBed::class, 'bed_id');
    }
}
