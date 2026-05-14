<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $company_id
 * @property int|null $employee_id
 * @property int|null $absence_type_id
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon|null $end_date
 * @property int $days_count
 * @property string $status
 * @property string|null $reason
 * @property string|null $proof_path
 * @property string|null $approved_by
 * @property string|null $rejected_reason
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\AbsenceType|null $absenceType
 */
class Absence extends Model
{
    use BelongsToCompany;
    use HasFactory;

    protected $table = 'absences';

    protected $fillable = [
        'company_id',
        'employee_id',
        'absence_type_id',
        'start_date',
        'end_date',
        'days_count',
        'status',
        'reason',
        'proof_path',
        'approved_by',
        'rejected_reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /** @return BelongsTo<AbsenceType, $this> */
    public function absenceType(): BelongsTo
    {
        return $this->belongsTo(AbsenceType::class, 'absence_type_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<static> $q
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', 'pending');
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<static> $q
     * @param int $employeeId
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForEmployee(Builder $q, int $employeeId): Builder
    {
        return $q->where('employee_id', $employeeId);
    }
}
