<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Planning\Domain\Models\Schedule;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class AttendanceLog extends Model
{
    use BelongsToCompany;
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'check_in' => 'datetime',
        'check_out' => 'datetime',
        'synced_from_offline' => 'boolean',
        'punch_meta' => 'array',
        'hours_worked' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
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

