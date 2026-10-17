<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Modules\TravelAgency\Domain\Enums\TravelVehiclePositionSource;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * #8548 (TRAVEL-OS) — une position RÉELLEMENT transmise par un dispositif
 * autorisé, conservée pour l'historique de trajet.
 *
 * Aucune ligne n'est créée par le serveur de sa propre initiative : la table
 * ne contient que ce qu'un boîtier GPS ou une application autorisée a envoyé.
 *
 * @property string $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property int $id
 * @property float $latitude
 * @property float $longitude
 * @property \Illuminate\Support\Carbon $recorded_at
 * @property \App\Modules\TravelAgency\Domain\Enums\TravelVehiclePositionSource $source
 * @property int $vehicle_id
 */
class TravelVehiclePositionLog extends Model
{
    use BelongsToCompany;

    /** Table en append-only : seule `created_at` est gérée par Eloquent. */
    public const UPDATED_AT = null;

    protected $table = 'travel_vehicle_position_logs';

    protected $fillable = [
        'vehicle_id',
        'latitude',
        'longitude',
        'source',
        'recorded_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'source' => TravelVehiclePositionSource::class,
        'recorded_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<TravelVehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(TravelVehicle::class, 'vehicle_id');
    }
}
