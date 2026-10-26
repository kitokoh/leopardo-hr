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
 * @property Carbon|null $due_at
 * @property Carbon|null $started_at
 * @property Carbon|null $scheduled_for
 * @property Carbon|null $completed_at
 * @property int|null $completed_by
 * @property string|null $completion_notes
 * @property string|null $description_redacted
 * @property int|null $created_by
 * @property string|null $external_id
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

    /**
     * #8578 (D4) — un seul état de naissance : `todo`.
     *
     * `open` et `pending` décrivaient le MÊME fait avec deux autres mots. Le
     * plus grave n'était pas le doublon mais son effet : `transitionTask()`
     * lisait `STATUS_OPEN` comme état de départ, or rien n'écrit jamais
     * `open` — le graphe de transition des tâches était donc MORT, toute
     * transition depuis une tâche réelle levant 422. Supprimer les synonymes
     * supprime la possibilité de cette divergence.
     */
    public const STATUS_TODO = 'todo';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_DONE = 'done';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [self::STATUS_TODO, self::STATUS_IN_PROGRESS, self::STATUS_DONE, self::STATUS_CANCELLED];

    /** État de naissance d'une tâche (aligné sur le défaut SQL). */
    public const DEFAULT_STATUS = self::STATUS_TODO;

    /**
     * #8578 (D4) — le modèle pose lui-même l'état de naissance.
     *
     * Même raison que sur `FuelIncident` : une tâche créée par un chemin qui ne
     * passe pas par le service (fixture, import) doit naître dans un état qui a
     * des transitions sortantes. Un défaut uniquement SQL laisse ce choix au
     * schéma physique, qui peut diverger du domaine.
     */
    protected static function booted(): void
    {
        static::creating(static function (self $task): void {
            if ($task->status === null || $task->status === '') {
                $task->status = self::DEFAULT_STATUS;
            }
        });
    }

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_HIGH = 'high';

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
        'due_at',
        'started_at',
        'scheduled_for',
        'completed_at',
        'completed_by',
        'completion_notes',
        'created_by',
        'external_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'station_id' => 'integer',
            'incident_id' => 'integer',
            'assigned_to' => 'integer',
            'scheduled_for' => 'date',
            'due_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'date',
            'completed_by' => 'integer',
        ];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(FuelIncident::class, 'incident_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }
}
