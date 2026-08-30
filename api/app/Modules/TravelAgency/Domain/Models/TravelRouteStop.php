<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelRouteStopFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Étape ordonnée d'une route (TRAVEL-206, issue #6019).
 *
 * `rank` détermine l'ordre de passage sur la route ; une même ville ne peut
 * apparaître deux fois sur la même route (contrainte DB
 * `travel_route_stops_company_route_city_unique`).
 */
class TravelRouteStop extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelRouteStopFactory> */
    use HasFactory;

    protected $fillable = [
        'route_id',
        'city_id',
        'rank',
        'is_stopover',
        'min_duration_min',
    ];

    protected $casts = [
        'rank' => 'integer',
        'is_stopover' => 'boolean',
        'min_duration_min' => 'integer',
    ];

    /**
     * @return BelongsTo<TravelRoute, $this>
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(TravelRoute::class);
    }

    /**
     * @return BelongsTo<TravelCity, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(TravelCity::class);
    }
}
