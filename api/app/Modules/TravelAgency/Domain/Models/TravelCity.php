<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Modules\TravelAgency\Domain\Enums\TravelRecordStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelCityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ville du référentiel TravelAgency (TRAVEL-202, issue #6015).
 *
 * Rattachement au pays par code ISO2 (référentiel tenant-scoped, pas de FK
 * inter-tenant). `region` = découpage administratif de premier niveau
 * (les découpages à 3 niveaux de gv-back sont planifiés en Phase 2).
 */
/**
 * @property int $id
 * @property string $company_id
 * @property string $country_iso2
 * @property string $name
 * @property string|null $region
 * @property float|null $latitude
 * @property float|null $longitude
 * @property TravelRecordStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class TravelCity extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelCityFactory> */
    use HasFactory;

    protected $fillable = [
        'country_iso2',
        'name',
        'region',
        'latitude',
        'longitude',
        'status',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'status' => TravelRecordStatus::class,
    ];

    /**
     * @return BelongsTo<TravelCountry, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(TravelCountry::class, 'country_iso2', 'iso2');
    }
}
