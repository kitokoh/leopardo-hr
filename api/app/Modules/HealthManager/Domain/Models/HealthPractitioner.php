<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Praticien de l'établissement — Issue #7786 (BC-31).
 *
 * Lié à un employé RH du tenant (`employee_id`, sans FK dure — pattern
 * EduTeacher : lien découplé du référentiel RH), rattaché optionnellement
 * à un service. Titre et statut bornés (CHECK en base). Le statut `active`
 * conditionne le rôle RBAC `health.practitioner` (HealthAccess).
 *
 * @property int $id
 * @property string $company_id
 * @property int $employee_id
 * @property int|null $department_id
 * @property string $title
 * @property string|null $license_number
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class HealthPractitioner extends Model
{
    use BelongsToCompany;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
    ];

    public const TITLE_DR = 'dr';

    public const TITLE_PR = 'pr';

    public const TITLE_MIDWIFE = 'midwife';

    public const TITLE_NURSE = 'nurse';

    public const TITLE_OTHER = 'other';

    /** @var list<string> */
    public const TITLES = [
        self::TITLE_DR,
        self::TITLE_PR,
        self::TITLE_MIDWIFE,
        self::TITLE_NURSE,
        self::TITLE_OTHER,
    ];

    protected $table = 'health_practitioners';

    protected $fillable = [
        'company_id',
        'employee_id',
        'department_id',
        'title',
        'license_number',
        'status',
    ];

    protected $casts = [
        'employee_id' => 'integer',
        'department_id' => 'integer',
        'title' => 'string',
        'status' => 'string',
    ];

    /**
     * @return BelongsTo<HealthDepartment, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(HealthDepartment::class, 'department_id');
    }

    /**
     * @return HasMany<HealthPractitionerSpecialty, $this>
     */
    public function practitionerSpecialties(): HasMany
    {
        return $this->hasMany(HealthPractitionerSpecialty::class, 'practitioner_id');
    }

    /**
     * @return HasMany<HealthAppointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(HealthAppointment::class, 'practitioner_id');
    }

    /**
     * @return HasMany<HealthConsultation, $this>
     */
    public function consultations(): HasMany
    {
        return $this->hasMany(HealthConsultation::class, 'practitioner_id');
    }
}
