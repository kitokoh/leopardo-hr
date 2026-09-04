<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Modules\TravelAgency\Domain\Enums\SeatStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelTripSeatFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Siège de l'inventaire d'un trajet (TRAVEL-208, issue #6021).
 *
 * Généré en transaction par `GenerateTripSeatsAction` à la création du
 * trajet — `booking_id`/`passenger_id` restent nullables tant que
 * `travel_bookings`/`travel_passengers` (TRAVEL-209) n'existent pas.
 */
class TravelTripSeat extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelTripSeatFactory> */
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'seat_number',
        'status',
        'booking_id',
        'passenger_id',
        'reserved_until',
    ];

    protected $casts = [
        'seat_number' => 'integer',
        'status' => SeatStatus::class,
        'reserved_until' => 'datetime',
    ];

    /**
     * @return BelongsTo<TravelTrip, $this>
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(TravelTrip::class, 'trip_id');
    }
}
