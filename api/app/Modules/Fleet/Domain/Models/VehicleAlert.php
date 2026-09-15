<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $vehicle_id
 * @property int|null $company_id
 * @property string $type
 * @property string|null $message
 * @property mixed $latitude
 * @property mixed $longitude
 * @property string $speed
 * @property bool $acknowledged
 * @property string|null $acknowledged_by
 * @property int|null $traccar_event_id
 * @property Carbon|null $created_at
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class VehicleAlert extends Model
{
    use BelongsToCompany;

    /**
     * #7399 — `vehicle_alerts` ne stocke pas de sévérité : l'information est
     * portée par `type` (enum de la migration `2026_05_11_000002`). Cette table
     * de correspondance est la SEULE source de vérité pour `severity`, exposé
     * par `VehicleAlertResource` et consommé par l'admin plateforme
     * (`FleetView.vue` : colonne Sévérité, `severityMap`, compteur d'alertes
     * critiques).
     *
     * @var array<string, string>
     */
    public const SEVERITY_BY_TYPE = [
        'sos' => 'critical',
        'speeding' => 'high',
        // Alias historique utilisé par certaines fixtures Traccar (le nom
        // exposé par l'API reste `speeding`).
        'overspeed' => 'high',
        'geofence_exit' => 'high',
        'low_fuel' => 'medium',
        'maintenance_due' => 'medium',
        'insurance_expiry' => 'medium',
        'geofence_enter' => 'low',
        'idle' => 'low',
    ];

    public $timestamps = false;

    protected $fillable = [
        'vehicle_id',
        'company_id',
        'type',
        'message',
        'latitude',
        'longitude',
        'speed',
        'acknowledged',
        'acknowledged_by',
        'traccar_event_id',
        'created_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'speed' => 'decimal:2',
            'acknowledged' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Sévérité dérivée du type d'alerte (#7399).
     *
     * Un type inconnu (valeur ajoutée à l'enum sans mise à jour de la table de
     * correspondance) retombe sur `low` : fail-open volontaire, une alerte
     * inconnue ne doit jamais être présentée comme critique.
     */
    public function getSeverityAttribute(): string
    {
        return self::SEVERITY_BY_TYPE[$this->type] ?? 'low';
    }

    /** @return BelongsTo<Employee, $this> */
    public function acknowledgedByUser(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'acknowledged_by');
    }
}
