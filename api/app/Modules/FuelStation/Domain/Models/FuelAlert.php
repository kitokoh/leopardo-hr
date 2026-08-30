<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Alerte FuelStation — FUEL-019 (#5813).
 *
 * Dédupliquée par `alert_key` unique par tenant : un re-scan ou un rejeu
 * d'événement ne crée jamais deux alertes identiques. Cycle :
 * open → acknowledged → resolved. Le payload ne contient JAMAIS de PII
 * ni de secrets.
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $station_id
 * @property string $event_type
 * @property string $severity info|warning|high|critical
 * @property string $alert_key
 * @property array<string, mixed> $payload
 * @property string $status open|acknowledged|resolved
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelAlert extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_alerts';

    public const SEVERITY_INFO = 'info';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_HIGH = 'high';

    public const SEVERITY_CRITICAL = 'critical';

    public const STATUS_OPEN = 'open';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUSES = [self::STATUS_OPEN, self::STATUS_ACKNOWLEDGED, self::STATUS_RESOLVED];

    protected $fillable = [
        'company_id',
        'station_id',
        'event_type',
        'severity',
        'alert_key',
        'payload',
        'status',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'station_id' => 'integer',
            'payload' => 'array',
            'resolved_at' => 'datetime',
        ];
    }
}
