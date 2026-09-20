<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Hospitalisation (admission) — Issue #7790 (BC-31).
 *
 * Invariants (spec §4, service, transaction + verrou) : lit `free` requis
 * → lit `occupied` ; transfert = ancien lit libéré + nouveau occupé ;
 * sortie = `discharged` + lit libéré. Statut borné (CHECK en base).
 *
 * @property int $id
 * @property string $company_id
 * @property int $patient_id
 * @property int $practitioner_id
 * @property int $department_id
 * @property int $bed_id
 * @property string|null $reason
 * @property Carbon $admitted_at
 * @property Carbon|null $expected_discharge_at
 * @property Carbon|null $discharged_at
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

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_ADMITTED,
        self::STATUS_TRANSFERRED,
        self::STATUS_DISCHARGED,
    ];

    protected $table = 'health_admissions';

    protected $fillable = [
        'company_id',
        'patient_id',
        'practitioner_id',
        'department_id',
        'bed_id',
        'reason',
        'admitted_at',
        'expected_discharge_at',
        'discharged_at',
        'status',
        'discharge_notes',
    ];

    protected $casts = [
        'patient_id' => 'integer',
        'practitioner_id' => 'integer',
        'department_id' => 'integer',
        'bed_id' => 'integer',
        'admitted_at' => 'datetime',
        'expected_discharge_at' => 'datetime',
        'discharged_at' => 'datetime',
        'status' => 'string',
    ];

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
