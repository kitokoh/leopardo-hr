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
 * Matière enseignée d'un établissement — Issue #5819 (EDU-003).
 *
 * Tenant-scoped (`company_id`, schéma tenant). `code` unique PAR TENANT
 * (UNIQUE company_id+code) ; les affectations enseignant → matière sont
 * portées par `EduTeacherSubject`.
 *
 * @property int $id
 * @property string $company_id
 * @property string $code
 * @property string $name
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, EduTeacherSubject> $teacherSubjects
 *
 * @mixin Builder<static>
 */
class EduSubject extends Model
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

    protected $table = 'edu_subjects';

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'status',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    /**
     * Affectations enseignant → matière (par année scolaire).
     *
     * @return HasMany<EduTeacherSubject, $this>
     */
    public function teacherSubjects(): HasMany
    {
        return $this->hasMany(EduTeacherSubject::class, 'subject_id');
    }
}
