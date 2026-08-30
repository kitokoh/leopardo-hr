<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Correction de présence (journal versionné) — Issue #5821 (EDU-005).
 *
 * Une correction n'écrase JAMAIS la ligne d'origine : elle enregistre
 * l'ancien statut, le nouveau, le motif, l'auteur et l'horodatage —
 * audit trail complet, version = ordre chronologique par (attendance_id).
 *
 * @property int $id
 * @property string $company_id
 * @property int $attendance_id
 * @property string $previous_status
 * @property string $new_status
 * @property string|null $reason
 * @property int|null $corrected_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class EduAttendanceCorrection extends Model
{
    use BelongsToCompany;

    protected $table = 'edu_attendance_corrections';

    protected $fillable = [
        'company_id',
        'attendance_id',
        'previous_status',
        'new_status',
        'reason',
        'corrected_by',
    ];

    protected $casts = [
        'attendance_id' => 'integer',
        'previous_status' => 'string',
        'new_status' => 'string',
    ];

    /**
     * @return BelongsTo<EduAttendance, $this>
     */
    public function attendance(): BelongsTo
    {
        return $this->belongsTo(EduAttendance::class, 'attendance_id');
    }
}
