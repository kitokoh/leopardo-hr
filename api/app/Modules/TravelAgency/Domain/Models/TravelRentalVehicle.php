<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Modules\TravelAgency\Domain\Enums\TravelRecordStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelRentalVehicleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Véhicule en location (TRAVEL-212, issue #6025).
 *
 * `owner_carrier_id` nullable : un véhicule de location peut appartenir à
 * l'agence elle-même, sans compagnie tierce propriétaire.
 */
class TravelRentalVehicle extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelRentalVehicleFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'title',
        'city_id',
        'price_per_day_minor',
        'currency',
        'available_from',
        'available_until',
        'owner_carrier_id',
        'status',
        'notes',
    ];

    protected $casts = [
        'price_per_day_minor' => 'integer',
        'available_from' => 'date',
        'available_until' => 'date',
        'status' => TravelRecordStatus::class,
    ];

    /**
     * @return BelongsTo<TravelCity, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(TravelCity::class, 'city_id');
    }

    /**
     * @return BelongsTo<TravelCarrier, $this>
     */
    public function ownerCarrier(): BelongsTo
    {
        return $this->belongsTo(TravelCarrier::class, 'owner_carrier_id');
    }

    /**
     * @return HasMany<TravelRentalVehicleImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(TravelRentalVehicleImage::class, 'vehicle_id')->orderBy('position');
    }
}
