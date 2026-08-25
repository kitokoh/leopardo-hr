<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Fermeture de journée du pointage (issue #5265).
 *
 * Un verrou quotidien par (entreprise, employé, date) : tant qu'il existe,
 * aucun nouveau pointage (check-in/check-out, import externe, approbation de
 * session géo) n'est accepté pour cet employé ce jour-là → 409
 * `ATTENDANCE_DAY_CLOSED`. Complémentaire du verrouillage de période
 * mensuelle (#5267, `attendance_period_closures`) qui cible les corrections.
 *
 * @property int $id
 * @property string $company_id
 * @property int $employee_id
 * @property Carbon $date
 * @property string $status locked|validated
 * @property int|null $locked_by
 * @property Carbon|null $locked_at
 * @property int|null $validated_by
 * @property Carbon|null $validated_at
 * @property string|null $note
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Employee|null $employee
 * @property-read Employee|null $lockedBy
 * @property-read Employee|null $validatedBy
 *
 * @mixin Builder<static>
 */
class AttendanceDayClosure extends Model
{
    use BelongsToCompany;

    public const STATUS_LOCKED = 'locked';

    public const STATUS_VALIDATED = 'validated';

    protected $table = 'attendance_day_closures';

    protected $fillable = [
        'company_id',
        'employee_id',
        'date',
        'status',
        'locked_by',
        'locked_at',
        'validated_by',
        'validated_at',
        'note',
    ];

    protected $casts = [
        'date' => 'date',
        'locked_at' => 'datetime',
        'validated_at' => 'datetime',
    ];

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'locked_by');
    }

    /** @return BelongsTo<Employee, $this> */
    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'validated_by');
    }

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_LOCKED;
    }

    public function isValidated(): bool
    {
        return $this->status === self::STATUS_VALIDATED;
    }
}
