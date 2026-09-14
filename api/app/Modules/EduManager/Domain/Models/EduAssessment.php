<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Évaluation scolaire — Issue #5823 (EDU-007).
 *
 * Tenant-scoped. Type borné (exam|quiz|homework|project), barème
 * `max_score > 0` et `coefficient > 0` garantis par CHECK. La publication
 * (timestamp) contrôle la visibilité des notes.
 *
 * @property int $id
 * @property string $company_id
 * @property int $class_id
 * @property int $subject_id
 * @property int $academic_year_id
 * @property string $title
 * @property string $type
 * @property string $coefficient
 * @property string $max_score
 * @property Carbon|null $assessment_date
 * @property Carbon|null $published_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $assessment_type
 * @property string $status
 *
 * @mixin Builder<static>
 */
class EduAssessment extends Model
{
    use BelongsToCompany;

    public const TYPE_EXAM = 'exam';

    public const TYPE_QUIZ = 'quiz';

    public const TYPE_HOMEWORK = 'homework';

    public const TYPE_PROJECT = 'project';

    /**
     * Contrôle continu (« test ») — vocabulaire v2 (#5823). La colonne
     * historique `type` porte une contrainte CHECK limitée à
     * exam|quiz|homework|project ; la génération v2 écrit `assessment_type`
     * (nullable, sans CHECK) → `TYPE_TEST` ne peut PAS vivre dans `type`.
     */
    public const TYPE_TEST = 'test';

    public const TYPES = [
        self::TYPE_EXAM,
        self::TYPE_QUIZ,
        self::TYPE_HOMEWORK,
        self::TYPE_PROJECT,
        self::TYPE_TEST,
    ];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PUBLISHED,
    ];

    protected $table = 'edu_assessments';

    protected $fillable = [
        'company_id',
        'class_id',
        'subject_id',
        'academic_year_id',
        'title',
        'type',
        'assessment_type',
        'status',
        'coefficient',
        'max_score',
        'assessment_date',
        'published_at',
        'created_by',
    ];

    protected $casts = [
        'class_id' => 'integer',
        'subject_id' => 'integer',
        'academic_year_id' => 'integer',
        'coefficient' => 'string',
        'max_score' => 'string',
        'assessment_date' => 'date',
        'published_at' => 'datetime',
        'status' => 'string',
    ];

    /**
     * @return HasMany<EduGrade, $this>
     */
    public function grades(): HasMany
    {
        return $this->hasMany(EduGrade::class, 'assessment_id');
    }

    /**
     * @return BelongsTo<EduClass, $this>
     */
    public function class(): BelongsTo
    {
        return $this->belongsTo(EduClass::class, 'class_id');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    /**
     * @return BelongsTo<EduSubject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(EduSubject::class, 'subject_id');
    }
}
