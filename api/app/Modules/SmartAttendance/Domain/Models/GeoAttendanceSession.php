<?php

declare(strict_types=1);

namespace App\Modules\SmartAttendance\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Models\AttendanceLog;
use App\Models\Site;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int         $id
 * @property int         $employee_id
 * @property string      $company_id
 * @property int|null    $site_id
 * @property Carbon      $started_at
 * @property Carbon|null $ended_at
 * @property int|null    $duration_seconds
 * @property float       $check_in_lat
 * @property float       $check_in_lng
 * @property int|null    $check_in_accuracy_meters
 * @property float|null  $check_out_lat
 * @property float|null  $check_out_lng
 * @property int|null    $check_out_accuracy_meters
 * @property string      $status               detected|pending_validation|approved|rejected|cancelled
 * @property int|null    $attendance_log_id
 * @property int|null    $validated_by
 * @property Carbon|null $validated_at
 * @property string|null $validation_note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee|null          $employee
 * @property-read Site|null              $site
 * @property-read Employee|null          $validatedBy
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EmployeeLocationEvent> $locationEvents
 * @property-read AttendanceLog|null     $attendanceLog
 */
class GeoAttendanceSession extends Model
{
    protected $table = 'geo_attendance_sessions';

    protected $fillable = [
        'employee_id',
        'company_id',
        'site_id',
        'started_at',
        'ended_at',
        'duration_seconds',
        'check_in_lat',
        'check_in_lng',
        'check_in_accuracy_meters',
        'check_out_lat',
        'check_out_lng',
        'check_out_accuracy_meters',
        'status',
        'attendance_log_id',
        'validated_by',
        'validated_at',
        'validation_note',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'ended_at'     => 'datetime',
        'validated_at' => 'datetime',
        'check_in_lat' => 'float',
        'check_in_lng' => 'float',
        'check_out_lat' => 'float',
        'check_out_lng' => 'float',
    ];

    // ── Statuts ──────────────────────────────────────────────────────────────

    public const STATUS_DETECTED           = 'detected';
    public const STATUS_PENDING_VALIDATION = 'pending_validation';
    public const STATUS_APPROVED           = 'approved';
    public const STATUS_REJECTED           = 'rejected';
    public const STATUS_CANCELLED          = 'cancelled';

    // ── Relations ────────────────────────────────────────────────────────────

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function attendanceLog(): BelongsTo
    {
        return $this->belongsTo(AttendanceLog::class, 'attendance_log_id');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'validated_by');
    }

    public function locationEvents(): HasMany
    {
        return $this->hasMany(EmployeeLocationEvent::class, 'geo_session_id');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function isOpen(): bool
    {
        return $this->ended_at === null
            && in_array($this->status, [self::STATUS_DETECTED, self::STATUS_PENDING_VALIDATION]);
    }

    public function durationFormatted(): string
    {
        if ($this->duration_seconds === null) {
            return '-';
        }

        $h = intdiv($this->duration_seconds, 3600);
        $m = intdiv($this->duration_seconds % 3600, 60);

        return sprintf('%dh%02dm', $h, $m);
    }

    public function durationHours(): float
    {
        return round(($this->duration_seconds ?? 0) / 3600, 2);
    }
}
