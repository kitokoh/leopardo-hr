<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Présence scolaire (état courant, corrections versionnées) — EDU-005
 * (issue #5821).
 *
 * UNIQUE (company_id, student_id, class_id, session_date, subject_id) :
 * une présence par élève/séance, enregistrement idempotent. Chaque
 * correction incrémente `version` et est AUDITÉE en append-only dans
 * `edu_attendance_corrections`.
 *
 * @property int $id
 * @property string $company_id
 * @property int $class_id
 * @property int $student_id
 * @property int|null $subject_id
 * @property Carbon $session_date
 * @property string|null $session_label
 * @property string $status present|absent|late|excused
 * @property string|null $reason
 * @property bool $justified
 * @property int $recorded_by
 * @property int $version
 * @property string|null $previous_status
 * @property string|null $correction_reason
 * @property int|null $corrected_by
 * @property Carbon|null $corrected_at
 *
 * @mixin Builder<static>
 */
class EduAttendanceRecord extends Model
{
    use BelongsToCompany;

    protected $table = 'edu_attendance_records';

    public const STATUS_PRESENT = 'present';

    public const STATUS_ABSENT = 'absent';

    public const STATUS_LATE = 'late';

    public const STATUS_EXCUSED = 'excused';

    public const STATUSES = [self::STATUS_PRESENT, self::STATUS_ABSENT, self::STATUS_LATE, self::STATUS_EXCUSED];

    protected $fillable = [
        'company_id',
        'class_id',
        'student_id',
        'subject_id',
        'session_date',
        'session_label',
        'status',
        'reason',
        'justified',
        'recorded_by',
        'version',
        'previous_status',
        'correction_reason',
        'corrected_by',
        'corrected_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'class_id' => 'integer',
            'student_id' => 'integer',
            'subject_id' => 'integer',
            'session_date' => 'date',
            'justified' => 'boolean',
            'recorded_by' => 'integer',
            'version' => 'integer',
            'corrected_by' => 'integer',
            'corrected_at' => 'datetime',
        ];
    }
}
