<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Tâche de maintenance (préventive ou corrective) — FUEL-010, issue #5804.
 *
 * Liée ou non à un incident (`incident_id` FK composite anti cross-tenant).
 * Échéance (`scheduled_at`), assignation et cycle de vie
 * planned → in_progress → completed | cancelled.
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $incident_id
 * @property string $title
 * @property string $task_type preventive|corrective
 * @property Carbon|null $scheduled_at
 * @property string $status planned|in_progress|completed|cancelled
 * @property int|null $assigned_to
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

    public const STATUS_PLANNED = 'planned';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [self::STATUS_PLANNED, self::STATUS_IN_PROGRESS, self::STATUS_COMPLETED, self::STATUS_CANCELLED];

    protected $fillable = [
        'company_id',
        'incident_id',
        'title',
        'task_type',
        'scheduled_at',
        'status',
        'assigned_to',
        'completed_at',
        'notes',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'incident_id' => 'integer',
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
            'assigned_to' => 'integer',
        ];
    }
}
