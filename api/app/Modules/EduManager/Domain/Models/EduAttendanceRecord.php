<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Enregistrement de présence scolaire — Issue #5821 (EDU-005).
 *
 * Un enregistrement = (classe, élève, date). Le statut est borné
 * (present|absent|late|excused) ; toute correction est VERSIONNÉE dans
 * edu_attendance_corrections (jamais d'écrasement silencieux).
 *
 * PII (classification `docs/architecture/EDUMANAGER_DONNEES.md`) :
 * `note` est une zone libre potentiellement personnelle (santé, motif
 * libre...) — jamais exposée hors tenant (RBAC EduAttendanceRecordPolicy) ;
 * `reason_code` reste un vocabulaire codifié (sick, family, other...).
 *
 * @property int $id
 * @property string $company_id
 * @property int $class_id
 * @property int $student_id
 * @property Carbon $attendance_date
 * @property string $status
 * @property string|null $reason_code
 * @property string|null $note
 * @property int|null $recorded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read EduStudent $student
 *
 * @mixin Builder<static>
 */
class EduAttendanceRecord extends Model
{
    use BelongsToCompany;

    public const STATUS_PRESENT = 'present';

    public const STATUS_ABSENT = 'absent';

    public const STATUS_LATE = 'late';

    public const STATUS_EXCUSED = 'excused';

    public const STATUSES = [
        self::STATUS_PRESENT,
        self::STATUS_ABSENT,
        self::STATUS_LATE,
        self::STATUS_EXCUSED,
    ];

    protected $table = 'edu_attendance_records';

    protected $fillable = [
        'company_id',
        'class_id',
        'student_id',
        'attendance_date',
        'status',
        'reason_code',
        'note',
        'recorded_by',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'status' => 'string',
        'recorded_by' => 'integer',
    ];

    /** @return BelongsTo<EduStudent, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(EduStudent::class, 'student_id');
    }

    /**
     * Classe associée (best-effort) — EduClass est livré par EDU-003
     * (#5819) : tant que le modèle n'existe pas, la relation retourne null
     * (fail-closed, aucun écrasement ni dépendance sur un lot non livré).
     *
     * @return BelongsTo<Model, $this>|null
     */
    public function class(): ?BelongsTo
    {
        /** @var class-string<Model> $classModel */
        $classModel = 'App\Modules\EduManager\Domain\Models\EduClass';

        if (! class_exists($classModel)) {
            return null;
        }

        return $this->belongsTo($classModel, 'class_id');
    }
}
