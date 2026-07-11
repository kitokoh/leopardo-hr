<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Planning\Domain\Models\Schedule;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $company_id
 * @property int $employee_id
 * @property string|null $date
 * @property Carbon|null $check_in
 * @property Carbon|null $check_out
 * @property float|null $hours_worked
 * @property float|null $overtime_hours
 * @property int|null $late_minutes
 * @property string|null $method
 * @property string|null $source_device_code
 * @property float|null $gps_lat
 * @property float|null $gps_lng
 * @property float|null $gps_accuracy
 * @property int|null $corrected_by
 * @property bool $synced_from_offline
 * @property array|null $punch_meta
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property int|null $schedule_id
 * @property-read \App\Core\Auth\Domain\Models\Employee|null $employee
 * @property-read \App\Modules\Planning\Domain\Models\Schedule|null $schedule
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class AttendanceLog extends Model
{
    use BelongsToCompany;
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'date'                 => 'date',
        'check_in'             => 'datetime',
        'check_out'            => 'datetime',
        'synced_from_offline'  => 'boolean',
        'punch_meta'           => 'array',
        'hours_worked'         => 'decimal:2',
        'overtime_hours'       => 'decimal:2',
        'late_minutes'         => 'integer',
        'gps_lat'              => 'decimal:8',
        'gps_lng'              => 'decimal:8',
        'gps_accuracy'         => 'float',
    ];

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<Schedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }
}

