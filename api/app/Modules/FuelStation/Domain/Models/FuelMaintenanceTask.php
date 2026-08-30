<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tâche de maintenance d'une station-service (FUEL-010, issue #5804).
 *
 * Préventive, corrective (dérivée d'un incident) ou inspection. Workflow
 * audité : open → in_progress → done | cancelled. Priorité et échéance
 * pilotent les alertes (FUEL-019). Description redacted (pas de PII).
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $station_id
 * @property int|null $incident_id
 * @property string $title
 * @property string|null $description_redacted
 * @property string $task_type
 * @property string $priority
 * @property string $status
 * @property int|null $assigned_to
 * @property Carbon|null $due_at
 * @property Carbon|null $started_at
 * @property int|null $completed_by
 * @property Carbon|null $completed_at
 * @property int|null $created_by
 * @property string|null $external_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelMaintenanceTask extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_maintenance_tasks';

    public const TYPE_PREVENTIVE = 'preventive';

    public const TYPE_CORRECTIVE = 'corrective';

    public const TYPE_INSPECTION = 'inspection';

    public const TYPES = [
        self::TYPE_PREVENTIVE,
        self::TYPE_CORRECTIVE,
        self::TYPE_INSPECTION,
    ];

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_CRITICAL = 'critical';

    public const PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_MEDIUM,
        self::PRIORITY_HIGH,
        self::PRIORITY_CRITICAL,
    ];

    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_DONE = 'done';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_IN_PROGRESS,
        self::STATUS_DONE,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'company_id',
        'station_id',
        'incident_id',
        'title',
        'description_redacted',
        'task_type',
        'priority',
        'status',
        'assigned_to',
        'due_at',
        'started_at',
        'completed_by',
        'completed_at',
        'created_by',
        'external_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'station_id' => 'integer',
            'incident_id' => 'integer',
            'due_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<FuelIncident, $this> */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(FuelIncident::class, 'incident_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }
}
