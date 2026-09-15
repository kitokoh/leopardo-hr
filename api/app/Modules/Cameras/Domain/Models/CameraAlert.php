<?php

declare(strict_types=1);

namespace App\Modules\Cameras\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Alerte caméra — calquée sur `FuelAlert` (#5813, issue #7427).
 *
 * Dédupliquée par `alert_key` **unique par tenant** : une rafale de mouvement
 * sur une même caméra ne produit qu'une alerte (et un seul push) par fenêtre
 * de regroupement. Cycle `open → acknowledged → resolved`.
 *
 * Une alerte référence toujours l'événement qui l'a déclenchée
 * (`camera_event_id`) : aucune alerte sans événement.
 *
 * @property int $id
 * @property string $company_id
 * @property int $camera_id
 * @property int|null $camera_event_id
 * @property string $type motion|person|vehicle|line_crossing|tamper
 * @property string $severity info|warning|high|critical
 * @property string $alert_key
 * @property array<string, mixed> $payload
 * @property string $status open|acknowledged|resolved
 * @property int|null $acknowledged_by
 * @property Carbon|null $acknowledged_at
 * @property int|null $resolved_by
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Camera|null $camera
 * @property-read CameraEvent|null $event
 * @property-read Employee|null $acknowledgedBy
 * @property-read Employee|null $resolvedBy
 *
 * @mixin Builder<static>
 */
class CameraAlert extends Model
{
    use BelongsToCompany;

    protected $table = 'camera_alerts';

    public const SEVERITY_INFO = 'info';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_HIGH = 'high';

    public const SEVERITY_CRITICAL = 'critical';

    public const SEVERITIES = [
        self::SEVERITY_INFO,
        self::SEVERITY_WARNING,
        self::SEVERITY_HIGH,
        self::SEVERITY_CRITICAL,
    ];

    public const STATUS_OPEN = 'open';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUSES = [self::STATUS_OPEN, self::STATUS_ACKNOWLEDGED, self::STATUS_RESOLVED];

    protected $fillable = [
        'company_id',
        'camera_id',
        'camera_event_id',
        'type',
        'severity',
        'alert_key',
        'payload',
        'status',
        'acknowledged_by',
        'acknowledged_at',
        'resolved_by',
        'resolved_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'camera_id' => 'integer',
            'camera_event_id' => 'integer',
            'acknowledged_by' => 'integer',
            'resolved_by' => 'integer',
            'payload' => 'array',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Camera, $this> */
    public function camera(): BelongsTo
    {
        return $this->belongsTo(Camera::class, 'camera_id');
    }

    /** @return BelongsTo<CameraEvent, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(CameraEvent::class, 'camera_event_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'acknowledged_by');
    }

    /** @return BelongsTo<Employee, $this> */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'resolved_by');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /** @param Builder<static> $query */
    public function scopeWithStatus(Builder $query, string $status): void
    {
        $query->where('status', $status);
    }

    /** @param Builder<static> $query */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', self::STATUS_OPEN);
    }
}
