<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $company_id
 * @property int|null $employee_id
 * @property int|null $schedule_id
 * @property \Illuminate\Support\Carbon $date
 * @property int $session_number
 * @property \Illuminate\Support\Carbon $check_in
 * @property \Illuminate\Support\Carbon $check_out
 * @property string $method
 * @property string|null $source_device_code
 * @property string|null $external_event_id
 * @property string $biometric_type
 * @property bool $synced_from_offline
 * @property string $status
 * @property string $hours_worked
 * @property string $overtime_hours
 * @property int $late_minutes
 * @property string $gps_lat
 * @property string $gps_lng
 * @property string|null $corrected_by
 * @property string|null $correction_note
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Employee|null $employee
 * @property-read \App\Models\Schedule|null $schedule
 */
class AttendanceLog extends Model
{
    use BelongsToCompany;
    use HasFactory;

    protected $table = 'attendance_logs';

    protected $fillable = [
        'company_id',
        'employee_id',
        'schedule_id',
        'date',
        'session_number',
        'check_in',
        'check_out',
        'method',
        'source_device_code',
        'external_event_id',
        'biometric_type',
        'synced_from_offline',
        'status',
        'hours_worked',
        'overtime_hours',
        'late_minutes',
        'gps_lat',
        'gps_lng',
        'corrected_by',
        'correction_note',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'check_in' => 'datetime',
        'check_out' => 'datetime',
        'hours_worked' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'synced_from_offline' => 'boolean',
        'late_minutes' => 'integer',
        'gps_lat' => 'decimal:8',
        'gps_lng' => 'decimal:8',
    ];

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /** @return BelongsTo<Schedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }
}
