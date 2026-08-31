<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelTouristSiteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Site touristique (TRAVEL-909, issue #6112). Annuaire géolocalisé, recherche par ville.
 */
/**
 * @property int $id
 * @property string $company_id
 * @property string $name
 * @property string|null $description_redacted
 * @property int $city_id
 * @property float|null $latitude
 * @property float|null $longitude
 * @property array<string, mixed>|null $images
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
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
