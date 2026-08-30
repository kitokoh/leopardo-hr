<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Incident équipement FuelStation — FUEL-010 (issue #5804).
 *
 * Cycle audité : open → assigned → in_progress → resolved → closed
 * (transitions validées en application via `FuelIncidentService`, jamais en
 * base). Résolution tracée (resolved_by/resolution_notes/resolved_at).
 *
 * @property int $id
 * @property string $company_id
 * @property int $station_id
 * @property string $equipment_type pump|tank|meter|other
 * @property int|null $equipment_id
 * @property string $severity low|medium|high|critical
 * @property string $status open|assigned|in_progress|resolved|closed
 * @property string $title
 * @property string|null $description
 * @property Carbon $occurred_at
 * @property int $reported_by
 * @property int|null $assigned_to
 * @property int|null $resolved_by
 * @property string|null $resolution_notes
 * @property Carbon|null $resolved_at
 * @property Carbon|null $closed_at
 *
 * @mixin Builder<static>
 */
class FuelIncident extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_incidents';

    public const STATUS_OPEN = 'open';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_ASSIGNED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_RESOLVED,
        self::STATUS_CLOSED,
    ];

    public const SEVERITIES = ['low', 'medium', 'high', 'critical'];

    public const EQUIPMENT_TYPES = ['pump', 'tank', 'meter', 'other'];

    protected $fillable = [
        'company_id',
        'station_id',
        'equipment_type',
        'equipment_id',
        'severity',
        'status',
        'title',
        'description',
        'occurred_at',
        'reported_by',
        'assigned_to',
        'resolved_by',
        'resolution_notes',
        'resolved_at',
        'closed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
