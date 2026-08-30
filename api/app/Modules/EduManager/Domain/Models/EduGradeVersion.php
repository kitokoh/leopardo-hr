<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Version d'une note publiée — Issue #5823 (EDU-007).
 *
 * Journal de VERSIONNAGE : une ligne est écrite AVANT chaque correction
 * d'une note publiée (previous_score → new_score + justification + acteur +
 * horodatage) — la modification d'une note publiée n'écrase jamais
 * silencieusement l'existant, elle l'audite (spec §6.3). `changed_at`
 * horodate la correction (timestampTz, défaut = now).
 *
 * PII : `reason` (justification) est bornée à 255 caractères (zone libre
 * contrôlée) — jamais exposée hors tenant (RBAC EduGradePolicy).
 *
 * @property int $id
 * @property string $company_id
 * @property int $grade_id
 * @property string|null $previous_score
 * @property string $new_score
 * @property string|null $previous_status
 * @property string $new_status
 * @property string|null $reason
 * @property int $changed_by
 * @property Carbon $changed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read EduGrade $grade
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
        'previous_score',
        'new_score',
        'previous_status',
        'new_status',
        'reason',
        'changed_by',
        'changed_at',
    ];

    protected $casts = [
        'previous_score' => 'decimal:2',
        'new_score' => 'decimal:2',
        'previous_status' => 'string',
        'new_status' => 'string',
        'changed_by' => 'integer',
        'changed_at' => 'datetime',
    ];

    /** @return BelongsTo<EduGrade, $this> */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(EduGrade::class, 'grade_id');
    }
}
