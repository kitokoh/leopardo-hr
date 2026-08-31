<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Correction VERSIONNÉE d'un enregistrement de présence — Issue #5821
 * (EDU-005).
 *
 * Une ligne est écrite AVANT chaque mise à jour d'un
 * EduAttendanceRecord (previous_status → new_status + motif) : la
 * modification d'une présence n'écrase jamais silencieusement l'existant,
 * elle l'audite. `corrected_at` horodate la correction (timestampTz).
 *
 * PII : `reason` (motif libre) peut contenir des informations personnelles —
 * jamais exposée hors tenant (RBAC manager uniquement).
 *
 * @property int $id
 * @property string $company_id
 * @property int $attendance_record_id
 * @property string $previous_status
 * @property string $new_status
 * @property string|null $reason
 * @property int $corrected_by
 * @property Carbon $corrected_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read EduAttendanceRecord $attendanceRecord
 *
 * @mixin Builder<static>
 */
class EduAttendanceCorrection extends Model
{
    use BelongsToCompany;

    protected $table = 'edu_attendance_corrections';

    protected $fillable = [
        'company_id',
        'attendance_record_id',
        'previous_status',
        'new_status',
        'reason',
        'corrected_by',
        'corrected_at',
    ];

    protected $casts = [
        'previous_status' => 'string',
        'new_status' => 'string',
        'corrected_by' => 'integer',
        'corrected_at' => 'datetime',
    ];

    /** @return BelongsTo<EduAttendanceRecord, $this> */
    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(EduAttendanceRecord::class, 'attendance_record_id');
    }
}
