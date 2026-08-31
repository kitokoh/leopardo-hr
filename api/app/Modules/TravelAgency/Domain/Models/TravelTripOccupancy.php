<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Read model d'occupation par trajet (TRAVEL-506, issue #6076).
 *
 * Recalculé par job idempotent (upsert par `(company_id, trip_id)`) —
 * la reprise donne un état identique.
 */
/**
 * @property int $id
 * @property string $company_id
 * @property string $trip_id
 * @property string $departure_date
 * @property int $total_seats
 * @property int $sold_seats
 * @property int $reserved_seats
 * @property int $free_seats
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
        'total_seats',
        'sold_seats',
        'reserved_seats',
        'free_seats',
        'occupancy_rate',
    ];

    protected $casts = [
        'departure_date' => 'date',
        'total_seats' => 'integer',
        'sold_seats' => 'integer',
        'reserved_seats' => 'integer',
        'free_seats' => 'integer',
        'occupancy_rate' => 'float',
    ];
}
