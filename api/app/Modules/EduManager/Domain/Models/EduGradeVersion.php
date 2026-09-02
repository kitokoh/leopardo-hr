<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Version d'une note (journal d'audit) — Issue #5823 (EDU-007).
 *
 * Chaque correction de note ajoute une version horodatée (score,
 * commentaire, auteur) — l'historique n'est jamais écrasé.
 *
 * @property int $id
 * @property string $company_id
 * @property int $grade_id
 * @property int $version
 * @property string $score
 * @property string|null $comment
 * @property int|null $changed_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class EduGradeVersion extends Model
{
    use BelongsToCompany;

    protected $table = 'edu_grade_versions';

    protected $fillable = [
        'company_id',
        'grade_id',
        'version',
        'score',
        'comment',
        'changed_by',
    ];

    protected $casts = [
        'grade_id' => 'integer',
        'version' => 'integer',
        'score' => 'string',
    ];

    /**
     * @return BelongsTo<EduGrade, $this>
     */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(EduGrade::class, 'grade_id');
    }
}
