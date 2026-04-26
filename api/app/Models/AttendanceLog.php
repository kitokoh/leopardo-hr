<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }
}
