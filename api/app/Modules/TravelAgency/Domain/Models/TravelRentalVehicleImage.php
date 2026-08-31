<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelRentalVehicleImageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Image d'un véhicule en location (TRAVEL-212, issue #6025).
 *
 * `position` unique par véhicule (contrainte DB
 * `travel_rental_images_company_vehicle_position_unique`).
 */
/**
 * @property int $id
 * @property string $company_id
 * @property string $vehicle_id
 * @property string $asset_id
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class TravelRentalVehicleImage extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelRentalVehicleImageFactory> */
    use HasFactory;

    protected $fillable = [
        'vehicle_id',
        'asset_id',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    /**
     * @return BelongsTo<TravelRentalVehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(TravelRentalVehicle::class, 'vehicle_id');
    }
}
