<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Note d'un élève à une évaluation — EDU-007 (issue #5823).
 *
 * VERSIONNÉE en append-only : UNIQUE (company_id, evaluation_id,
 * student_id, version). Une note publiée est immuable ; la correction crée
 * une NOUVELLE version (l'original reste consultable, audit complet).
 *
 * @property int $id
 * @property string $company_id
 * @property int $evaluation_id
 * @property int $student_id
 * @property float $score
 * @property string $status draft|published
 * @property string|null $comment
 * @property int $version
 * @property int $entered_by
 * @property string|null $correction_reason
 * @property int|null $corrected_by
 * @property Carbon|null $corrected_at
 *
 * @mixin Builder<static>
 */
class EduGradeEntry extends Model
{
    use BelongsToCompany;

    protected $table = 'edu_grade_entries';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'company_id',
        'evaluation_id',
        'student_id',
        'score',
        'status',
        'comment',
        'version',
        'entered_by',
        'correction_reason',
        'corrected_by',
        'corrected_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'evaluation_id' => 'integer',
            'student_id' => 'integer',
            'score' => 'float',
            'version' => 'integer',
            'entered_by' => 'integer',
            'corrected_by' => 'integer',
            'corrected_at' => 'datetime',
        ];
    }
}
