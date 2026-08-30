<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelTripPriceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tarif d'un trajet par classe (TRAVEL-207, issue #6020).
 *
 * Montants en unités mineures (minor units, ex. centimes) — jamais de
 * flottant. Un seul prix par (trip, classe) : contrainte DB
 * `travel_trip_prices_company_trip_class_unique`.
 */
class TravelTripPrice extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelTripPriceFactory> */
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'class_id',
        'adult_price_minor',
        'child_price_minor',
        'currency',
    ];

    protected $casts = [
        'adult_price_minor' => 'integer',
        'child_price_minor' => 'integer',
    ];

    /**
     * @return BelongsTo<TravelTrip, $this>
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(TravelTrip::class);
    }

    /**
     * @return BelongsTo<TravelClass, $this>
     */
    public function travelClass(): BelongsTo
    {
        return $this->belongsTo(TravelClass::class, 'class_id');
    }
}
