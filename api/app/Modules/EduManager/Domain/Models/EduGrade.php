<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Note d'un élève à une évaluation — Issue #5823 (EDU-007).
 *
 * Une seule note courante par (évaluation, élève) et tenant (UNIQUE).
 * Toute correction incrémente `version` et écrit une ligne
 * `edu_grade_versions` (historique complet, jamais d'écrasement).
 *
 * @property int $id
 * @property string $company_id
 * @property int $assessment_id
 * @property int $student_id
 * @property string $score
 * @property string|null $comment
 * @property int|null $graded_by
 * @property string $status
 * @property int $version
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class EduGrade extends Model
{
    use BelongsToCompany;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_CORRECTED = 'corrected';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PUBLISHED,
        self::STATUS_CORRECTED,
    ];

    protected $table = 'edu_grades';

    protected $fillable = [
        'company_id',
        'assessment_id',
        'student_id',
        'score',
        'comment',
        'graded_by',
        'status',
        'version',
        'published_at',
    ];

    protected $casts = [
        'assessment_id' => 'integer',
        'student_id' => 'integer',
        'score' => 'string',
        'status' => 'string',
        'version' => 'integer',
        'published_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<EduAssessment, $this>
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(EduAssessment::class, 'assessment_id');
    }

    /**
     * @return BelongsTo<EduStudent, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(EduStudent::class, 'student_id');
    }
}
