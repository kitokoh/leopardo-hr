<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Évaluation d'un établissement — Issue #5823 (EDU-007).
 *
 * Tenant-scoped (`company_id`, schéma tenant). Une évaluation lie une
 * classe, une matière et une année scolaire ; `max_score` est le barème
 * (CHECK > 0 en base) et `coefficient` pondère la note dans le bulletin.
 * Cycle de vie : draft → published (notes verrouillées, correction
 * versionnée via EduGradeVersion) → archived.
 *
 * PII (spec §6.3) : `title` reste un libellé court et borné (120
 * caractères) — les notes (EduGrade.comment) sont bornées à 255, jamais de
 * zone libre non bornée susceptible de porter des données sensibles.
 *
 * @property int $id
 * @property string $company_id
 * @property int $class_id
 * @property int $subject_id
 * @property int $academic_year_id
 * @property string $title
 * @property string $assessment_type
 * @property string $max_score
 * @property string $coefficient
 * @property Carbon $assessment_date
 * @property string $status
 * @property Carbon|null $published_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read EduClass $class
 * @property-read EduSubject $subject
 * @property-read Collection<int, EduGrade> $grades
 *
 * @mixin Builder<static>
 */
class EduAssessment extends Model
{
    use BelongsToCompany;

    public const TYPE_EXAM = 'exam';

    public const TYPE_TEST = 'test';

    public const TYPE_QUIZ = 'quiz';

    public const TYPE_HOMEWORK = 'homework';

    public const TYPES = [
        self::TYPE_EXAM,
        self::TYPE_TEST,
        self::TYPE_QUIZ,
        self::TYPE_HOMEWORK,
    ];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PUBLISHED,
        self::STATUS_ARCHIVED,
    ];

    protected $table = 'edu_assessments';

    protected $fillable = [
        'company_id',
        'class_id',
        'subject_id',
        'academic_year_id',
        'title',
        'assessment_type',
        'max_score',
        'coefficient',
        'assessment_date',
        'status',
        'published_at',
        'created_by',
    ];

    protected $casts = [
        'class_id' => 'integer',
        'subject_id' => 'integer',
        'academic_year_id' => 'integer',
        'max_score' => 'decimal:2',
        'coefficient' => 'decimal:2',
        'assessment_date' => 'date',
        'status' => 'string',
        'published_at' => 'datetime',
        'created_by' => 'integer',
    ];

    /**
     * Classe concernée par l'évaluation.
     *
     * @return BelongsTo<EduClass, $this>
     */
    public function class(): BelongsTo
    {
        return $this->belongsTo(EduClass::class, 'class_id');
    }

    /**
     * Matière évaluée.
     *
     * @return BelongsTo<EduSubject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(EduSubject::class, 'subject_id');
    }

    /**
     * Notes de l'évaluation (une par élève, UNIQUE assessment+student).
     *
     * @return HasMany<EduGrade, $this>
     */
    public function grades(): HasMany
    {
        return $this->hasMany(EduGrade::class, 'assessment_id');
    }
}
