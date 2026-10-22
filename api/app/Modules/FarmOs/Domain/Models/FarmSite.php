<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Farm OS — Exploitation / site (bâtiment, entrepôt, site de production).
 *
 * Une exploitation regroupe des parcelles, des opérations et du matériel.
 * Les superficies portent TOUJOURS leur provenance (`total_area_source`) :
 * déclarée, importée ou mesurée (ADR-FARM-0003).
 *
 * @property int $id
 * @property string|null $company_id
 * @property string $name
 * @property string $reference
 * @property string $kind
 * @property string|null $country_code
 * @property string|null $region
 * @property string|null $address
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string $timezone
 * @property string|null $total_area_value
 * @property string|null $total_area_unit
 * @property string|null $total_area_source
 * @property string $status
 * @property array<mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class FarmSite extends Model
{
    use BelongsToCompany;

    protected $table = 'farm_sites';

    protected $fillable = [
        'company_id',
        'name',
        'reference',
        'kind',
        'country_code',
        'region',
        'address',
        'latitude',
        'longitude',
        'timezone',
        'total_area_value',
        'total_area_unit',
        'total_area_source',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * @return HasMany<Parcel, $this>
     */
    public function parcels(): HasMany
    {
        return $this->hasMany(Parcel::class, 'farm_site_id');
    }

    /**
     * @return HasMany<FarmOperation, $this>
     */
    public function operations(): HasMany
    {
        return $this->hasMany(FarmOperation::class, 'farm_site_id');
    }

    public function hasGeometry(): bool
    {
        return false;
    }
}
