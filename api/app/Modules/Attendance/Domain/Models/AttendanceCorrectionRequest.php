<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class AttendanceCorrectionRequest extends Model
{
    protected $guarded = [];

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

