<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Tâche de maintenance FuelStation — FUEL-010 (issue #5804).
 *
 * Préventive ou corrective, priorité allowlistée, affectation optionnelle,
 * cycle pending → in_progress → completed|cancelled. Rattachable à un
 * incident (FK interne `incident_id`).
 *
 * @property int $id
 * @property string $company_id
 * @property int $station_id
 * @property int|null $incident_id
 * @property string $type preventive|corrective
 * @property string $priority low|medium|high
 * @property string $status pending|in_progress|completed|cancelled
 * @property string $title
 * @property string|null $description
 * @property string|null $scheduled_for
 * @property int|null $assigned_to
 * @property int|null $completed_by
 * @property Carbon|null $completed_at
 *
 * @mixin Builder<static>
 */
class FuelMaintenanceTask extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_maintenance_tasks';

    public const TYPE_PREVENTIVE = 'preventive';

    public const TYPE_CORRECTIVE = 'corrective';

    public const PRIORITIES = ['low', 'medium', 'high'];

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'company_id',
        'station_id',
        'incident_id',
        'type',
        'priority',
        'status',
        'title',
        'description',
        'scheduled_for',
        'assigned_to',
        'completed_by',
        'completed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'scheduled_for' => 'date',
            'completed_at' => 'datetime',
        ];
    }
}
