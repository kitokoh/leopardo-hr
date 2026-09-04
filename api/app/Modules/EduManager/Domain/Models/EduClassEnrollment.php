<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Inscription d'un élève dans une classe — Issue #5827 (EDU-011).
 *
 * UNIQUE (company_id, class_id, student_id) → idempotence ; le retrait passe
 * par soft-status (inactive|archived), l'historique n'est jamais écrasé.
 * Alimente la présence (EDU-005) et l'espace enseignant (EDU-012).
 *
 * @property int $id
 * @property string $company_id
 * @property int $class_id
 * @property int $student_id
 * @property int $academic_year_id
 * @property Carbon $enrolled_at
 * @property string $status
 * @property int|null $enrolled_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class EduClassEnrollment extends Model
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

    protected $table = 'edu_class_enrollments';

    protected $fillable = [
        'company_id',
        'class_id',
        'student_id',
        'academic_year_id',
        'enrolled_at',
        'status',
        'enrolled_by',
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'status' => 'string',
    ];

    /** @return BelongsTo<EduClass, $this> */
    public function class(): BelongsTo
    {
        return $this->belongsTo(EduClass::class, 'class_id');
    }

    /** @return BelongsTo<EduStudent, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(EduStudent::class, 'student_id');
    }
}
