<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Praticien d'un établissement de santé — HC-002 (#7786, BC-30).
 *
 * `employee_id` lie le praticien à un employé RH du tenant (lien découplé,
 * pattern EduTeacher — pas de FK). Spécialités en n-n via le pivot
 * `health_practitioner_specialty` (FK composites, cross-tenant impossible).
 * Un praticien ACTIF confère la permission `health.practitioner`
 * (HealthAccess::isPractitioner) : lecture du registre patients (HC-003).
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $employee_id
 * @property string $display_name
 * @property string|null $license_number
 * @property string|null $title
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

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_ARCHIVED,
    ];

    protected $table = 'health_practitioners';

    protected $fillable = [
        'employee_id',
        'display_name',
        'license_number',
        'title',
        'status',
    ];

    /**
     * @return BelongsToMany<HealthSpecialty, $this>
     */
    public function specialties(): BelongsToMany
    {
        return $this->belongsToMany(
            HealthSpecialty::class,
            'health_practitioner_specialty',
            'practitioner_id',
            'specialty_id'
        )->withTimestamps();
    }
}
