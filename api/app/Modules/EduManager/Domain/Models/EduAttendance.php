<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Présence scolaire — Issue #5821 (EDU-005).
 *
 * Tenant-scoped. UNIQUE (class_id, student_id, attendance_date) par tenant →
 * saisie idempotente (firstOrCreate). Les corrections sont VERSIONNÉES dans
 * `edu_attendance_corrections` (jamais d'UPDATE silencieux).
 *
 * @property int $id
 * @property string $company_id
 * @property int $class_id
 * @property int $student_id
 * @property Carbon $attendance_date
 * @property string $status
 * @property string|null $reason
 * @property string|null $justification
 * @property int|null $recorded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class EduAttendance extends Model
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

    protected $table = 'edu_attendances';

    protected $fillable = [
        'company_id',
        'class_id',
        'student_id',
        'attendance_date',
        'status',
        'reason',
        'justification',
        'recorded_by',
    ];

    protected $casts = [
        'class_id' => 'integer',
        'student_id' => 'integer',
        'attendance_date' => 'date',
        'status' => 'string',
    ];

    /**
     * @return BelongsTo<EduClass, $this>
     */
    public function class(): BelongsTo
    {
        return $this->belongsTo(EduClass::class, 'class_id');
    }

    /**
     * @return BelongsTo<EduStudent, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(EduStudent::class, 'student_id');
    }
}
