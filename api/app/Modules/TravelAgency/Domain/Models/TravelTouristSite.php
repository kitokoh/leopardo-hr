<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelTouristSiteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Site touristique (TRAVEL-909, issue #6112). Annuaire géolocalisé, recherche par ville.
 */
class TravelTouristSite extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelTouristSiteFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id', 'name', 'description_redacted', 'city_id', 'latitude', 'longitude', 'images', 'status',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'images' => 'array',
    ];
}
