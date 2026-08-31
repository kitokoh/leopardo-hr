<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * TRAVEL-909 (#6112) — Site touristique (tenant-scoped, annuaire).
 *
 * @property int $id
 * @property string $company_id
 * @property string $name
 * @property string|null $description_redacted
 * @property int|null $city_id
 * @property string|null $latitude
 * @property string|null $longitude
 * @property array<int, string>|null $images
 * @property string $status
 *
 * @mixin Builder<static>
 */
class TravelTouristSite extends Model
{
    use BelongsToCompany;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    protected $table = 'travel_tourist_sites';

    protected $fillable = [
        'company_id',
        'name',
        'description_redacted',
        'city_id',
        'latitude',
        'longitude',
        'images',
        'status',
    ];

    protected $casts = [
        'images' => 'array',
    ];
}
