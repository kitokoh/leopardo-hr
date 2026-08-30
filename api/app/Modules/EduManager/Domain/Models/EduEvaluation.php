<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Évaluation d'une classe/matière — EDU-007 (issue #5823).
 *
 * Statut draft → published (immuable) → archived. `max_score` > 0 (CHECK) ;
 * toute note publiée est versionnée (append-only), jamais modifiée en place.
 *
 * @property int $id
 * @property string $company_id
 * @property int $class_id
 * @property int $subject_id
 * @property int $academic_year_id
 * @property string $title
 * @property string $type exam|quiz|homework|continuous
 * @property float $coefficient
 * @property float $max_score
 * @property string $status draft|published|archived
 * @property int $created_by
 * @property int|null $published_by
 * @property Carbon|null $published_at
 *
 * @mixin Builder<static>
 */
class EduEvaluation extends Model
{
    use BelongsToCompany;

    protected $table = 'edu_evaluations';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_PUBLISHED, self::STATUS_ARCHIVED];

    public const TYPE_EXAM = 'exam';

    public const TYPE_QUIZ = 'quiz';

    public const TYPE_HOMEWORK = 'homework';

    public const TYPE_CONTINUOUS = 'continuous';

    public const TYPES = [self::TYPE_EXAM, self::TYPE_QUIZ, self::TYPE_HOMEWORK, self::TYPE_CONTINUOUS];

    protected $fillable = [
        'company_id',
        'class_id',
        'subject_id',
        'academic_year_id',
        'title',
        'type',
        'coefficient',
        'max_score',
        'status',
        'created_by',
        'published_by',
        'published_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'class_id' => 'integer',
            'subject_id' => 'integer',
            'academic_year_id' => 'integer',
            'coefficient' => 'float',
            'max_score' => 'float',
            'created_by' => 'integer',
            'published_by' => 'integer',
            'published_at' => 'datetime',
        ];
    }
}
