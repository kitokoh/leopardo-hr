<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tâche de maintenance (préventive/corrective) — FUEL-010 (#5804).
 *
 * Liée optionnellement à un incident (`incident_id` nullable, FK composite
 * anti cross-tenant). Cycle : pending → in_progress → done | cancelled.
 * `due_at` : échéance pilotant les alertes de maintenance (FUEL-019).
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $station_id
 * @property int|null $incident_id
 * @property string $task_type preventive|corrective
 * @property string $priority low|medium|high
 * @property string $status pending|in_progress|done|cancelled
 * @property string $title
 * @property string|null $description
 * @property Carbon|null $due_at
 * @property int|null $assigned_to
 * @property int|null $completed_by
 * @property Carbon|null $completed_at
 * @property string|null $notes
 * @property int|null $created_by
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

    public const TYPES = [self::TYPE_PREVENTIVE, self::TYPE_CORRECTIVE];

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITIES = [self::PRIORITY_LOW, self::PRIORITY_MEDIUM, self::PRIORITY_HIGH];

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_DONE = 'done';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_IN_PROGRESS, self::STATUS_DONE, self::STATUS_CANCELLED];

    protected $fillable = [
        'company_id',
        'station_id',
        'incident_id',
        'task_type',
        'priority',
        'status',
        'title',
        'description',
        'due_at',
        'assigned_to',
        'completed_by',
        'completed_at',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'station_id' => 'integer',
            'incident_id' => 'integer',
            'due_at' => 'datetime',
            'assigned_to' => 'integer',
            'completed_by' => 'integer',
            'completed_at' => 'datetime',
            'created_by' => 'integer',
        ];
    }

    /** @return BelongsTo<FuelIncident, $this> */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(FuelIncident::class, 'incident_id');
    }
}
