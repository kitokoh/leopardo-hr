<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Modules\TravelAgency\Domain\Enums\MeansOfTransport;
use App\Modules\TravelAgency\Domain\Enums\TripStatus;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Instance datée d'une route (TRAVEL-207, issue #6020).
 *
 * `carrier_id`/`vehicle_id` nullables (préparation avant affectation).
 * `total_seats` pilote la génération transactionnelle des sièges
 * (TRAVEL-208, #6021).
 */
class TravelTrip extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<Database\Factories\TravelTripFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'route_id',
        'carrier_id',
        'vehicle_id',
        'departure_date',
        'departure_time',
        'arrival_date',
        'arrival_time',
        'means_of_transport',
        'total_seats',
        'status',
        'published_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'departure_date' => 'date',
        'arrival_date' => 'date',
        'total_seats' => 'integer',
        'means_of_transport' => MeansOfTransport::class,
        'status' => TripStatus::class,
        'published_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<TravelRoute, $this>
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(TravelRoute::class);
    }

    /**
     * @return BelongsTo<TravelCarrier, $this>
     */
    public function carrier(): BelongsTo
    {
        return $this->belongsTo(TravelCarrier::class);
    }

    /**
     * @return BelongsTo<TravelVehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(TravelVehicle::class);
    }

    /**
     * @return HasMany<TravelTripPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(TravelTripPrice::class);
    }
}
