<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Modules\TravelAgency\Domain\Enums\TravelRecordStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelRouteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ligne ville→ville de la verticale TravelAgency (TRAVEL-206, issue #6019).
 *
 * `origin_city_id` et `destination_city_id` référencent le référentiel
 * `travel_cities` (TRAVEL-202) ; une route ne peut relier une ville à
 * elle-même (contrainte DB `travel_routes_origin_destination_distinct_check`).
 */
class TravelRoute extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelRouteFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'origin_city_id',
        'destination_city_id',
        'distance_km',
        'duration_min',
        'status',
        'carrier_id',
        'external_id',
    ];

    protected $casts = [
        'distance_km' => 'integer',
        'duration_min' => 'integer',
        'status' => TravelRecordStatus::class,
    ];

    /**
     * @return BelongsTo<TravelCity, $this>
     */
    public function originCity(): BelongsTo
    {
        return $this->belongsTo(TravelCity::class, 'origin_city_id');
    }

    /**
     * @return BelongsTo<TravelCity, $this>
     */
    public function destinationCity(): BelongsTo
    {
        return $this->belongsTo(TravelCity::class, 'destination_city_id');
    }

    /**
     * @return HasMany<TravelRouteStop, $this>
     */
    public function stops(): HasMany
    {
        return $this->hasMany(TravelRouteStop::class, 'route_id')->orderBy('rank');
    }
}
