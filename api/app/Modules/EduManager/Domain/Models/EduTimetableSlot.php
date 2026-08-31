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
 * Un créneau lie une classe (`edu_classes`), une matière (`edu_subjects`)
 * et un enseignant (`edu_teachers`) à une plage horaire d'un jour de la
 * semaine (1 = lundi … 7 = dimanche). Isolation stricte via `company_id`
 * uuid NON nullable (BelongsToCompany) ; les FK composites
 * (class_id/subject_id/teacher_id, company_id) rendent un rattachement
 * cross-tenant structurellement impossible.
 *
 * Les chevauchements (même classe OU même enseignant, même jour) sont
 * contrôlés au niveau application par `TimetableService::create` — le
 * schéma ne porte qu'une garde UNIQUE exacte (classe, jour, heure de début).
 *
 * Les modèles `EduClass`, `EduSubject` et `EduTeacher` sont livrés par les
 * issues EDU-003/004/005 (#5819/#5820/#5821) du même lot.
 *
 * @property int $id
 * @property string $company_id
 * @property int $class_id
 * @property int $subject_id
 * @property int $teacher_id
 * @property int $day_of_week
 * @property string $start_time
 * @property string $end_time
 * @property string|null $room
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class EduTimetableSlot extends Model
{
    use BelongsToCompany;

    public const DAY_MONDAY = 1;

    public const DAY_TUESDAY = 2;

    public const DAY_WEDNESDAY = 3;

    public const DAY_THURSDAY = 4;

    public const DAY_FRIDAY = 5;

    public const DAY_SATURDAY = 6;

    public const DAY_SUNDAY = 7;

    /** 1 = lundi … 7 = dimanche (ISO-8601). */
    public const DAYS = [
        self::DAY_MONDAY,
        self::DAY_TUESDAY,
        self::DAY_WEDNESDAY,
        self::DAY_THURSDAY,
        self::DAY_FRIDAY,
        self::DAY_SATURDAY,
        self::DAY_SUNDAY,
    ];

    protected $table = 'edu_timetable_slots';

    protected $fillable = [
        'company_id',
        'class_id',
        'subject_id',
        'teacher_id',
        'day_of_week',
        'start_time',
        'end_time',
        'room',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        // Heures locales du tenant — conservées telles quelles (pas de
        // conversion de fuseau au stockage : le jour/heure sont locaux).
        'start_time' => 'string',
        'end_time' => 'string',
    ];

    /**
     * Classe concernée (FK composite — même tenant garanti en base).
     *
     * @return BelongsTo<EduClass, $this>
     */
    public function class(): BelongsTo
    {
        return $this->belongsTo(EduClass::class, 'class_id');
    }

    /**
     * Matière enseignée (FK composite — même tenant garanti en base).
     *
     * @return BelongsTo<EduSubject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(EduSubject::class, 'subject_id');
    }

    /**
     * Enseignant affecté (FK composite — même tenant garanti en base).
     *
     * @return BelongsTo<EduTeacher, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(EduTeacher::class, 'teacher_id');
    }
}
