<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Enseignant d'un établissement — Issue #5819 (EDU-003).
 *
 * Tenant-scoped (`company_id`, schéma tenant). `employee_id` lie le dossier
 * RH du tenant SANS FK (pattern `edu_guardians.employee_id`, #5818-3) et
 * reste unique par tenant (PostgreSQL autorise plusieurs NULL).
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $employee_id
 * @property string $display_name
 * @property string|null $specialization
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, EduTeacherSubject> $teacherSubjects
 *
 * @mixin Builder<static>
 */
class EduTeacher extends Model
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

    protected $table = 'edu_teachers';

    protected $fillable = [
        'company_id',
        'employee_id',
        'display_name',
        'specialization',
        'status',
    ];

    protected $casts = [
        'employee_id' => 'integer',
        'status' => 'string',
    ];

    /**
     * Affectations matière de cet enseignant (par année scolaire).
     *
     * @return HasMany<EduTeacherSubject, $this>
     */
    public function teacherSubjects(): HasMany
    {
        return $this->hasMany(EduTeacherSubject::class, 'teacher_id');
    }
}
