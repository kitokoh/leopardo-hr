<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Incident équipement d'une station-service — FUEL-010 (#5804).
 *
 * Workflow audité : reported → assigned → in_progress → resolved → closed.
 * Chaque transition est un événement de domaine ; `resolution_notes` est
 * obligatoire avant resolved. `idempotency_key` unique par tenant (rejeu
 * sûr). Pièces jointes contrôlées via `fuel_incident_attachments`.
 *
 * @property int $id
 * @property string $company_id
 * @property int $station_id
 * @property string $equipment_type pump|tank|meter|other
 * @property int|null $equipment_id
 * @property string $severity low|medium|high|critical
 * @property string $status reported|assigned|in_progress|resolved|closed
 * @property string $title
 * @property string $description
 * @property int|null $reported_by
 * @property int|null $assigned_to
 * @property string|null $resolution_notes
 * @property Carbon|null $resolved_at
 * @property string $idempotency_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelIncident extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_incidents';

    public const EQUIPMENT_PUMP = 'pump';

    public const EQUIPMENT_TANK = 'tank';

    public const EQUIPMENT_METER = 'meter';

    public const EQUIPMENT_OTHER = 'other';

    public const EQUIPMENT_TYPES = [
        self::EQUIPMENT_PUMP,
        self::EQUIPMENT_TANK,
        self::EQUIPMENT_METER,
        self::EQUIPMENT_OTHER,
    ];

    public const SEVERITY_LOW = 'low';

    public const SEVERITY_MEDIUM = 'medium';

    public const SEVERITY_HIGH = 'high';

    public const SEVERITY_CRITICAL = 'critical';

    public const SEVERITIES = [
        self::SEVERITY_LOW,
        self::SEVERITY_MEDIUM,
        self::SEVERITY_HIGH,
        self::SEVERITY_CRITICAL,
    ];

    public const STATUS_REPORTED = 'reported';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_REPORTED,
        self::STATUS_ASSIGNED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_RESOLVED,
        self::STATUS_CLOSED,
    ];

    /** Transitions autorisées du workflow (état → états suivants). */
    public const TRANSITIONS = [
        self::STATUS_REPORTED => [self::STATUS_ASSIGNED, self::STATUS_IN_PROGRESS, self::STATUS_CLOSED],
        self::STATUS_ASSIGNED => [self::STATUS_IN_PROGRESS, self::STATUS_CLOSED],
        self::STATUS_IN_PROGRESS => [self::STATUS_RESOLVED],
        self::STATUS_RESOLVED => [self::STATUS_CLOSED],
        self::STATUS_CLOSED => [],
    ];

    protected $fillable = [
        'company_id',
        'station_id',
        'equipment_type',
        'equipment_id',
        'severity',
        'status',
        'title',
        'description',
        'reported_by',
        'assigned_to',
        'resolution_notes',
        'resolved_at',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'station_id' => 'integer',
            'equipment_id' => 'integer',
            'reported_by' => 'integer',
            'assigned_to' => 'integer',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<FuelStation, $this> */
    public function station(): BelongsTo
    {
        return $this->belongsTo(FuelStation::class, 'station_id');
    }

    /** @return HasMany<FuelMaintenanceTask, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(FuelMaintenanceTask::class, 'incident_id');
    }

    /** @return HasMany<FuelIncidentAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(FuelIncidentAttachment::class, 'incident_id');
    }
}
