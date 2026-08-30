<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Incident d'équipement d'une station — FUEL-010, issue #5804.
 *
 * Workflow audité (chaque transition trace `audit_logs`, catégorie
 * fuel_incident) ; notifications par événements (FUEL-019) sans
 * exposition PII. `assigned_to` référence un employé DU MÊME tenant
 * (contrôle au niveau service).
 *
 * @property int $id
 * @property string $company_id
 * @property int $station_id
 * @property string $equipment_type pump|tank|meter|other
 * @property int|null $equipment_id
 * @property string $title
 * @property string $description
 * @property string $priority low|medium|high|critical
 * @property string $status reported|assigned|in_progress|resolved|closed
 * @property int|null $reported_by
 * @property int|null $assigned_to
 * @property string|null $resolution_notes
 * @property Carbon|null $resolved_at
 * @property Carbon|null $closed_at
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

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_CRITICAL = 'critical';

    public const PRIORITIES = [self::PRIORITY_LOW, self::PRIORITY_MEDIUM, self::PRIORITY_HIGH, self::PRIORITY_CRITICAL];

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

    protected $fillable = [
        'company_id',
        'station_id',
        'equipment_type',
        'equipment_id',
        'title',
        'description',
        'priority',
        'status',
        'reported_by',
        'assigned_to',
        'resolution_notes',
        'resolved_at',
        'closed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'station_id' => 'integer',
            'equipment_id' => 'integer',
            'assigned_to' => 'integer',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /** @return HasMany<FuelIncidentAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(FuelIncidentAttachment::class, 'incident_id');
    }
}
