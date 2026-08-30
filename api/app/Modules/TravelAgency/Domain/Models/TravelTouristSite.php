<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Modules\TravelAgency\Domain\Enums\TravelRecordStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelTouristSiteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Site touristique de l'annuaire (TRAVEL-909, issue #6112).
 *
 * @property int $id
 * @property string $company_id
 * @property string $name
 * @property string|null $description_redacted
 * @property int|null $city_id
 * @property string|null $latitude
 * @property string|null $longitude
 * @property int|null $image_asset_id
 * @property TravelRecordStatus $status
 *
 * @mixin Builder<static>
 */
class TravelTouristSite extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelTouristSiteFactory> */
    use HasFactory;

    protected $table = 'travel_tourist_sites';

    protected $fillable = [
        'company_id',
        'name',
        'description_redacted',
        'city_id',
        'latitude',
        'longitude',
        'image_asset_id',
        'status',
    ];

    protected $casts = [
        'status' => TravelRecordStatus::class,
    ];

    /**
     * @return BelongsTo<TravelCity, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(TravelCity::class, 'city_id');
    }
}
