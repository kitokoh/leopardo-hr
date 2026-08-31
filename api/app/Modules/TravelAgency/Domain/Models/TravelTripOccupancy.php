<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * TRAVEL-506 (#6076) — Read model : occupation par trajet.
 * Recalculé par job idempotent (upsert par clé unique tenant+trajet).
 *
 * @mixin Builder<static>
use Illuminate\Database\Eloquent\Model;

/**
 * Read model d'occupation par trajet (TRAVEL-506, issue #6076).
 *
 * Recalculé par job idempotent (upsert par `(company_id, trip_id)`) —
 * la reprise donne un état identique.
 */
class TravelTripOccupancy extends Model
{
    use BelongsToCompany;

    protected $table = 'travel_trip_occupancy';

    protected $fillable = [
        'company_id',
        'trip_id',
        'departure_date',
        'seats_sold',
        'total_seats',
        'total_seats',
        'sold_seats',
        'reserved_seats',
        'free_seats',
        'occupancy_rate',
    ];

    protected $casts = [
        'departure_date' => 'date',
        'seats_sold' => 'integer',
        'total_seats' => 'integer',
        'total_seats' => 'integer',
        'sold_seats' => 'integer',
        'reserved_seats' => 'integer',
        'free_seats' => 'integer',
        'occupancy_rate' => 'float',
    ];
}
