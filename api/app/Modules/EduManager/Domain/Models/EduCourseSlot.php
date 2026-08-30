<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Créneau d'emploi du temps — Issue #5822 (EDU-006).
 *
 * Créneau hebdomadaire récurrent d'une classe (matière + enseignant +
 * salle). Horaires exprimés dans la timezone du campus (Campus.timezone).
 * Les conflits (classe ou enseignant déjà pris) sont contrôlés par
 * `EduCourseSlotService`.
 *
 * @property int $id
 * @property string $company_id
 * @property int $class_id
 * @property int $subject_id
 * @property int $academic_year_id
 * @property int|null $teacher_id
 * @property int $day_of_week
 * @property string $start_time
 * @property string $end_time
 * @property string|null $room
 * @property string $status
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class EduCourseSlot extends Model
{
    use BelongsToCompany;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_CANCELLED,
    ];

    protected $table = 'edu_course_slots';

    protected $fillable = [
        'company_id',
        'class_id',
        'subject_id',
        'academic_year_id',
        'teacher_id',
        'day_of_week',
        'start_time',
        'end_time',
        'room',
        'status',
        'created_by',
    ];

    protected $casts = [
        'class_id' => 'integer',
        'subject_id' => 'integer',
        'academic_year_id' => 'integer',
        'teacher_id' => 'integer',
        'day_of_week' => 'integer',
        'start_time' => 'string',
        'end_time' => 'string',
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
     * @return BelongsTo<EduSubject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(EduSubject::class, 'subject_id');
    }
}
