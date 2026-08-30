<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Note d'une évaluation — Issue #5823 (EDU-007).
 *
 * Tenant-scoped (`company_id`, schéma tenant). Une note = (évaluation,
 * élève) ; UNIQUE(company_id, assessment_id, student_id) en base. Statut :
 * draft (modifiable) → published (IMMUABLE hors correction auditable —
 * GradeService::correctGrade versionne dans edu_grade_versions AVANT de
 * modifier, jamais d'écrasement silencieux).
 *
 * PII (spec §6.3) : `comment` est une zone BORNÉE à 255 caractères — un
 * commentaire libre non borné susceptible de porter des données sensibles
 * est rejeté côté serveur (GradeService).
 *
 * @property int $id
 * @property string $company_id
 * @property int $assessment_id
 * @property int $student_id
 * @property string $score
 * @property string|null $comment
 * @property string $status
 * @property int|null $graded_by
 * @property Carbon|null $graded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read EduAssessment $assessment
 * @property-read EduStudent $student
 *
 * @mixin Builder<static>
 */
class EduGrade extends Model
{
    use BelongsToCompany;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PUBLISHED,
    ];

    protected $table = 'edu_grades';

    protected $fillable = [
        'company_id',
        'assessment_id',
        'student_id',
        'score',
        'comment',
        'status',
        'graded_by',
        'graded_at',
    ];

    protected $casts = [
        'assessment_id' => 'integer',
        'student_id' => 'integer',
        'score' => 'decimal:2',
        'comment' => 'string',
        'status' => 'string',
        'graded_by' => 'integer',
        'graded_at' => 'datetime',
    ];

    /** @return BelongsTo<EduAssessment, $this> */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(EduAssessment::class, 'assessment_id');
    }

    /** @return BelongsTo<EduStudent, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(EduStudent::class, 'student_id');
    }
}
