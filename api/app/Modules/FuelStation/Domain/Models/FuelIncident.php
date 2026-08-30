<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Incident équipement d'une station-service (FUEL-010, issue #5804).
 *
 * Workflow audité : reported → assigned → resolved → closed. La description
 * est REDACTED (aucune PII, aucun secret) ; les pièces jointes ne sont
 * jamais stockées ici — seules leurs métadonnées contrôlées (MIME, taille).
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $station_id
 * @property string $category
 * @property string $severity
 * @property string $description_redacted
 * @property string $status
 * @property int $reported_by
 * @property Carbon $reported_at
 * @property int|null $assigned_to
 * @property Carbon|null $assigned_at
 * @property int|null $resolved_by
 * @property Carbon|null $resolved_at
 * @property int|null $closed_by
 * @property Carbon|null $closed_at
 * @property array<int, array<string, mixed>>|null $attachments_metadata
 * @property string|null $external_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelIncident extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_incidents';

    public const CATEGORY_EQUIPMENT = 'equipment';

    public const CATEGORY_FUEL_LEAK = 'fuel_leak';

    public const CATEGORY_SAFETY = 'safety';

    public const CATEGORY_CASH = 'cash';

    public const CATEGORY_OTHER = 'other';

    public const CATEGORIES = [
        self::CATEGORY_EQUIPMENT,
        self::CATEGORY_FUEL_LEAK,
        self::CATEGORY_SAFETY,
        self::CATEGORY_CASH,
        self::CATEGORY_OTHER,
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

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_REPORTED,
        self::STATUS_ASSIGNED,
        self::STATUS_RESOLVED,
        self::STATUS_CLOSED,
    ];

    protected $fillable = [
        'company_id',
        'station_id',
        'category',
        'severity',
        'description_redacted',
        'status',
        'reported_by',
        'reported_at',
        'assigned_to',
        'assigned_at',
        'resolved_by',
        'resolved_at',
        'closed_by',
        'closed_at',
        'attachments_metadata',
        'external_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'station_id' => 'integer',
            'reported_at' => 'datetime',
            'assigned_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'attachments_metadata' => 'array',
        ];
    }

    /** @return BelongsTo<Employee, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reported_by');
    }

    /** @return BelongsTo<Employee, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }

    /** @return HasMany<FuelMaintenanceTask, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(FuelMaintenanceTask::class, 'incident_id');
    }
}
