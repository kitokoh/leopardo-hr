<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Modules\TravelAgency\Domain\Enums\TravelRecordStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelVehicleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Véhicule de la flotte propre de l'agence (TRAVEL-205, issue #6018).
 *
 * `carrier_id` nullable : un véhicule propre à l'agence n'appartient à
 * aucune compagnie tierce (`travel_carriers`), il peut aussi être rattaché
 * à un transporteur si l'agence opère pour son compte.
 */
class TravelVehicle extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelVehicleFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'registration_number',
        'seat_capacity',
        'carrier_id',
        'status',
        'notes',
    ];

    protected $casts = [
        'seat_capacity' => 'integer',
        'status' => TravelRecordStatus::class,
    ];

    /**
     * @return BelongsTo<TravelCarrier, $this>
     */
    public function carrier(): BelongsTo
    {
        return $this->belongsTo(TravelCarrier::class);
    }
}
