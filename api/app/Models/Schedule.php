<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $company_id
 * @property string $name
 * @property string|null $start_time
 * @property string|null $end_time
 * @property int $break_minutes
 * @property array<mixed> $work_days
 * @property int $late_tolerance_minutes
 * @property string $overtime_threshold_daily
 * @property string $overtime_threshold_weekly
 * @property bool $is_default
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Schedule extends Model
{
    use BelongsToCompany;
    use HasFactory;

    protected $table = 'schedules';

    protected $fillable = [
        'company_id',
        'name',
        'start_time',
        'end_time',
        'break_minutes',
        'work_days',
        'late_tolerance_minutes',
        'overtime_threshold_daily',
        'overtime_threshold_weekly',
        'is_default',
    ];

    protected $casts = [
        'break_minutes' => 'integer',
        'work_days' => 'array',
        'late_tolerance_minutes' => 'integer',
        'overtime_threshold_daily' => 'decimal:2',
        'overtime_threshold_weekly' => 'decimal:2',
        'is_default' => 'boolean',
    ];
}
