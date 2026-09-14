<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 * @property int|null $academic_year_id
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
        // v2 (#5819) : l'affectation est bornée à une année scolaire. La
        // colonne existait et était NULLABLE, mais absente du `fillable` :
        // le champ était silencieusement PERDU à la création et la relation
        // `academicYear` ressortait nulle.
        'academic_year_id',
        'status',
        'created_by',
    ];

    protected $casts = [
        'class_id' => 'integer',
        'subject_id' => 'integer',
        'teacher_id' => 'integer',
        'academic_year_id' => 'integer',
        'status' => 'string',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(EduTeacher::class, 'teacher_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(EduSubject::class, 'subject_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(EduAcademicYear::class, 'academic_year_id');
    }
}
