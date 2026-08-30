<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Affectation enseignant → matière pour une année scolaire — Issue #5819
 * (EDU-003).
 *
 * Tenant-scoped (`company_id`, schéma tenant). Les FK composites
 * (teacher_id, company_id) / (subject_id, company_id) /
 * (academic_year_id, company_id) rendent toute affectation cross-tenant
 * structurellement impossible (violation FK en base) ; UNIQUE par tenant
 * sur le quadruplet (company_id, teacher_id, subject_id, academic_year_id).
 *
 * @property int $id
 * @property string $company_id
 * @property int $teacher_id
 * @property int $subject_id
 * @property int $academic_year_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read EduTeacher $teacher
 * @property-read EduSubject $subject
 * @property-read EduAcademicYear $academicYear
 *
 * @mixin Builder<static>
 */
class EduTeacherSubject extends Model
{
    use BelongsToCompany;

    protected $table = 'edu_teacher_subjects';

    protected $fillable = [
        'company_id',
        'teacher_id',
        'subject_id',
        'academic_year_id',
    ];

    protected $casts = [
        'teacher_id' => 'integer',
        'subject_id' => 'integer',
        'academic_year_id' => 'integer',
    ];

    /** @return BelongsTo<EduTeacher, $this> */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(EduTeacher::class, 'teacher_id');
    }

    /** @return BelongsTo<EduSubject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(EduSubject::class, 'subject_id');
    }

    /** @return BelongsTo<EduAcademicYear, $this> */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(EduAcademicYear::class, 'academic_year_id');
    }
}
