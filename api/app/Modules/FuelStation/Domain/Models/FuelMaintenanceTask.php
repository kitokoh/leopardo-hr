<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Tâche de maintenance préventive/corrective FuelStation — FUEL-010
 * (issue #5804).
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $station_id
 * @property int|null $incident_id
 * @property string $task_type preventive|corrective
 * @property string $title
 * @property string|null $description
 * @property string $priority low|medium|high
 * @property string $status todo|in_progress|done|cancelled
 * @property int|null $assigned_to
 * @property Carbon|null $scheduled_for
 * @property Carbon|null $completed_at
 * @property int|null $completed_by
 * @property string|null $completion_notes
 *
 * @mixin Builder<static>
 */
class FuelMaintenanceTask extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_maintenance_tasks';

    public const STATUS_TODO = 'todo';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_DONE = 'done';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [self::STATUS_TODO, self::STATUS_IN_PROGRESS, self::STATUS_DONE, self::STATUS_CANCELLED];

    protected $fillable = [
        'company_id',
        'station_id',
        'incident_id',
        'task_type',
        'title',
        'description',
        'priority',
        'status',
        'assigned_to',
        'scheduled_for',
        'completed_at',
        'completed_by',
        'completion_notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'station_id' => 'integer',
            'incident_id' => 'integer',
            'assigned_to' => 'integer',
            'scheduled_for' => 'date',
            'completed_at' => 'date',
            'completed_by' => 'integer',
        ];
    }
}
