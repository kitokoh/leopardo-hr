<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Modules\TravelAgency\Domain\Enums\TravelRecordStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelCountryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Pays du référentiel TravelAgency (TRAVEL-201, issue #6014).
 *
 * Tenant-scoped : chaque tenant reçoit son référentiel complet au provisioning
 * (TravelGeoSeederService) et peut le personnaliser via l'API.
 */
/**
 * @property int $id
 * @property string $company_id
 * @property string $iso2
 * @property string $iso3
 * @property string $name
 * @property int|null $phone_code
 * @property TravelRecordStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class TravelCountry extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelCountryFactory> */
    use HasFactory;

    protected $fillable = [
        'iso2',
        'iso3',
        'name',
        'phone_code',
        'status',
    ];

    protected $casts = [
        'phone_code' => 'integer',
        'status' => TravelRecordStatus::class,
    ];

    /**
     * @return HasMany<TravelCity, $this>
     */
    public function cities(): HasMany
    {
        return $this->hasMany(TravelCity::class, 'country_iso2', 'iso2');
    }
}
