<?php

declare(strict_types=1);

namespace App\Modules\HR\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Événement de carrière (plans de carrière, issue #5259).
 *
 * Trace le parcours : promotion, augmentation, transfert, changement de
 * poste. Workflow : pending → approved → applied (ou rejected). Le passage à
 * `applied` met à jour l'employé (position_id, department_id, salary_base) —
 * impact paie sans intervention manuelle (spec
 * docs/specifications/ISSUE_5259_CAREER_PLANS.md).
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $employee_id
 * @property string $type
 * @property string $status
 * @property int|null $from_position_id
 * @property int|null $to_position_id
 * @property int|null $from_department_id
 * @property int|null $to_department_id
 * @property float|null $from_salary
 * @property float|null $to_salary
 * @property Carbon|null $effective_date
 * @property string $reason
 * @property string|null $notes
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $applied_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee|null $employee
 * @property-read Position|null $fromPosition
 * @property-read Position|null $toPosition
 * @property-read Department|null $fromDepartment
 * @property-read Department|null $toDepartment
 * @property-read Employee|null $approver
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class CareerEvent extends Model
{
    use BelongsToCompany;
    use HasFactory;

    protected $table = 'career_events';

    protected $fillable = [
        'company_id', 'employee_id', 'type', 'status',
        'from_position_id', 'to_position_id',
        'from_department_id', 'to_department_id',
        'from_salary', 'to_salary',
        'effective_date', 'reason', 'notes',
        'approved_by', 'approved_at', 'applied_at',
    ];

    protected $casts = [
        'from_salary' => 'float',
        'to_salary' => 'float',
        'effective_date' => 'date',
        'approved_at' => 'datetime',
        'applied_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /** @return BelongsTo<Position, $this> */
    public function fromPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'from_position_id');
    }

    /** @return BelongsTo<Position, $this> */
    public function toPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'to_position_id');
    }

    /** @return BelongsTo<Department, $this> */
    public function fromDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'from_department_id');
    }

    /** @return BelongsTo<Department, $this> */
    public function toDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'to_department_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    /**
     * @param  Builder<static>  $q
     * @return Builder<static>
     */
    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', 'pending');
    }

    /**
     * @param  Builder<static>  $q
     * @return Builder<static>
     */
    public function scopeApproved(Builder $q): Builder
    {
        return $q->where('status', 'approved');
    }

    /**
     * @param  Builder<static>  $q
     * @return Builder<static>
     */
    public function scopeApplied(Builder $q): Builder
    {
        return $q->where('status', 'applied');
    }

    /**
     * @param  Builder<static>  $q
     * @return Builder<static>
     */
    public function scopeForEmployee(Builder $q, int $id): Builder
    {
        return $q->where('employee_id', $id);
    }
}
