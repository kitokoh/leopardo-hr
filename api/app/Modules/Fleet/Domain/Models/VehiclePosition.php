<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * #7401 — trace GPS d'un véhicule (une ligne = une position Traccar).
 *
 * Alimentée par `FleetTrackingSyncService` (commande `leopardo:fleet:sync` et
 * endpoint `POST /tracking/sync-positions`). L'unicité
 * `(company_id, traccar_position_id)` garantit l'idempotence du rejeu.
 *
 * @property int $id
 * @property int $vehicle_id
 * @property string|null $company_id
 * @property int|null $device_id
 * @property int|null $traccar_position_id
 * @property mixed $latitude
 * @property mixed $longitude
 * @property string|null $speed_kmh
 * @property Carbon $recorded_at
 * @property Carbon|null $created_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class VehiclePosition extends Model
{
    use BelongsToCompany;

    public $timestamps = false;

    protected $fillable = [
        'vehicle_id',
        'company_id',
        'device_id',
        'traccar_position_id',
        'latitude',
        'longitude',
        'speed_kmh',
        'recorded_at',
        'created_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'speed_kmh' => 'decimal:2',
            'recorded_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
