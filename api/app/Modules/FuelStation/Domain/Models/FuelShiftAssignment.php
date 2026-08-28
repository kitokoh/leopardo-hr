<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Affectation d'un employé à un shift pour une date donnée (FUEL-005,
 * issue #5799).
 *
 * Un employé ne peut être affecté qu'à des shifts de SON tenant
 * (validation `company_id` dans `FuelShiftService::assign()`) et ses
 * affectations du même jour ne doivent pas se chevaucher dans le temps
 * (contrôle `assertNoOverlap()`).
 *
 * @property string $id
 * @property string $company_id
 * @property string $shift_id
 * @property int $employee_id
 * @property Carbon $assignment_date
 * @property string $status scheduled|confirmed|completed|cancelled
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read FuelShift|null $shift
 * @property-read Employee|null $employee
 *
 * @mixin Builder<static>
 */
class FuelShiftAssignment extends Model
{
    use BelongsToCompany;
    use HasUuids;

    protected $table = 'fuel_shift_assignments';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_SCHEDULED,
        self::STATUS_CONFIRMED,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'company_id',
        'shift_id',
        'employee_id',
        'assignment_date',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'assignment_date' => 'date',
    ];

    /** @return BelongsTo<FuelShift, $this> */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(FuelShift::class, 'shift_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
