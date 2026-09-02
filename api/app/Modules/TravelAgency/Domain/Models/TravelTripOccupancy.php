<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * TRAVEL-506 (#6076) — Read model : occupation par trajet.
 * Recalculé par job idempotent (upsert par clé unique tenant+trajet).
 *
 * @mixin Builder<static>
 */
/**
 * @property int $id
 * @property string $company_id
 * @property string $trip_id
 * @property string $departure_date
 * @property int $seats_sold
 * @property int $total_seats
 * @property float $occupancy_rate
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
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
        'occupancy_rate',
    ];

    protected $casts = [
        'departure_date' => 'date',
        'seats_sold' => 'integer',
        'total_seats' => 'integer',
        'occupancy_rate' => 'float',
    ];


}