<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Affectation enseignant → matière pour une classe — Issue #5819 (EDU-003).
 *
 * Un enseignant (employee_id RH du même tenant) enseigne une matière dans
 * une classe. L'historique est conservé via `status` (inactive ≠ suppression).
 *
 * @property int $id
 * @property string $company_id
 * @property int $class_id
 * @property int $subject_id
 * @property int $teacher_id
 * @property string $status
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class EduTeacherSubject extends Model
{
    use BelongsToCompany;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
    ];

    protected $table = 'edu_teacher_subjects';

    protected $fillable = [
        'company_id',
        'class_id',
        'subject_id',
        'teacher_id',
        'status',
        'created_by',
    ];

    protected $casts = [
        'class_id' => 'integer',
        'subject_id' => 'integer',
        'teacher_id' => 'integer',
        'status' => 'string',
    ];
}
