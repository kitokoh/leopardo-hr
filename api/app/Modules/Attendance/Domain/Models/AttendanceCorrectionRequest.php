<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $company_id
 * @property int $employee_id
 * @property int|null $attendance_log_id
 * @property \Illuminate\Support\Carbon $date
 * @property \Illuminate\Support\Carbon $requested_check_in
 * @property \Illuminate\Support\Carbon|null $requested_check_out
 * @property string $reason
 * @property string $status
 * @property int|null $reviewed_by
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class AttendanceCorrectionRequest extends Model
{
    protected $fillable = [
        'company_id',
        'employee_id',
        'attendance_log_id',
        'date',
        'requested_check_in',
        'requested_check_out',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'date' => 'date',
        'requested_check_in' => 'datetime',
        'requested_check_out' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<AttendanceLog, $this> */
    public function attendanceLog(): BelongsTo
    {
        return $this->belongsTo(AttendanceLog::class);
    }
}

