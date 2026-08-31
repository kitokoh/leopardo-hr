<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Incident équipement FuelStation — FUEL-010 (issue #5804).
 *
 * Workflow audité : open → in_progress → resolved → closed. Chaque
 * transition est horodatée et attribuée (reported_by, assigned_to,
 * resolved_by, closed_by). Notification à l'assignation sans PII
 * (FUEL-019).
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $station_id
 * @property string $equipment_type pump|tank|meter|other
 * @property int|null $equipment_id
 * @property string $severity low|medium|high|critical
 * @property string $status open|in_progress|resolved|closed
 * @property string $title
 * @property string|null $description
 * @property int|null $reported_by
 * @property int|null $assigned_to
 * @property Carbon $occurred_at
 * @property Carbon|null $resolved_at
 * @property int|null $resolved_by
 * @property string|null $resolution_notes
 * @property Carbon|null $closed_at
 * @property int|null $closed_by
 * @property string|null $closure_notes
 *
 * @mixin Builder<static>
 */
class FuelIncident extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_incidents';

    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [self::STATUS_OPEN, self::STATUS_IN_PROGRESS, self::STATUS_RESOLVED, self::STATUS_CLOSED];

    public const SEVERITY_LOW = 'low';

    public const SEVERITY_MEDIUM = 'medium';

    public const SEVERITY_HIGH = 'high';

    public const SEVERITY_CRITICAL = 'critical';

    public const SEVERITIES = [self::SEVERITY_LOW, self::SEVERITY_MEDIUM, self::SEVERITY_HIGH, self::SEVERITY_CRITICAL];

    public const EQUIPMENT_PUMP = 'pump';

    public const EQUIPMENT_TANK = 'tank';

    public const EQUIPMENT_METER = 'meter';

    public const EQUIPMENT_OTHER = 'other';

    public const EQUIPMENT_TYPES = [self::EQUIPMENT_PUMP, self::EQUIPMENT_TANK, self::EQUIPMENT_METER, self::EQUIPMENT_OTHER];

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
        'occurred_at',
        'resolved_at',
        'resolved_by',
        'resolution_notes',
        'closed_at',
        'closed_by',
        'closure_notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'station_id' => 'integer',
            'equipment_id' => 'integer',
            'reported_by' => 'integer',
            'assigned_to' => 'integer',
            'occurred_at' => 'datetime',
            'resolved_at' => 'datetime',
            'resolved_by' => 'integer',
            'closed_at' => 'datetime',
            'closed_by' => 'integer',
        ];
    }

    /** @return HasMany<FuelIncidentAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(FuelIncidentAttachment::class, 'incident_id');
    }
}
