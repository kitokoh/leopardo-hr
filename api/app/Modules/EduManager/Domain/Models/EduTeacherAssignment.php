<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Affectation enseignant → (classe, matière, année) — EDU-003 (#5819).
 *
 * @property int $id
 * @property string $company_id
 * @property int $class_id
 * @property int $subject_id
 * @property int $teacher_id
 * @property int $academic_year_id
 * @property string $status active|archived
 *
 * @mixin Builder<static>
 */
class EduTeacherAssignment extends Model
{
    use BelongsToCompany;

    protected $table = 'edu_teacher_assignments';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'company_id',
        'class_id',
        'subject_id',
        'teacher_id',
        'academic_year_id',
        'status',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'class_id' => 'integer',
            'subject_id' => 'integer',
            'teacher_id' => 'integer',
            'academic_year_id' => 'integer',
        ];
    }

    /** @return BelongsTo<EduTeacher, $this> */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(EduTeacher::class, 'teacher_id');
    }
}
