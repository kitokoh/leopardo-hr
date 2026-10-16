<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FarmOs\Domain\Enums\AreaSource;
use App\Modules\FarmOs\Domain\Enums\AreaUnit;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Farm OS — Parcelle.
 *
 * Provenance de la superficie (ADR-FARM-0003) : `area_source` ∈ declared,
 * imported, measured — voir {@see \App\Modules\FarmOs\Domain\Services\FarmAreaGuard}.
 * La géométrie cartographique est NULLABLE ; sans elle, aucune vue carte ni
 * superficie « mesurée » ne doit être produite.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $farm_site_id
 * @property string $name
 * @property string $reference
 * @property string|null $area_value
 * @property string|null $area_unit
 * @property string|null $area_source
 * @property string|null $soil_type
 * @property string|null $irrigation_type
 * @property string $status
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class Parcel extends Model
{
    use BelongsToCompany;

    protected $table = 'farm_parcels';

    /** Parcelle active (par défaut), en repos, ou sortie du registre. */
    public const STATUSES = ['active', 'fallow', 'retired'];

    protected $fillable = [
        'company_id',
        'farm_site_id',
        'name',
        'reference',
        'area_value',
        'area_unit',
        'area_source',
        'geometry_wkt',
        'geometry_source',
        'soil_type',
        'irrigation_type',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<FarmSite, $this>
     */
    public function farmSite(): BelongsTo
    {
        return $this->belongsTo(FarmSite::class, 'farm_site_id');
    }

    /**
     * @return HasMany<CropCycle, $this>
     */
    public function cropCycles(): HasMany
    {
        return $this->hasMany(CropCycle::class, 'parcel_id');
    }

    /**
     * @return HasMany<FarmOperation, $this>
     */
    public function operations(): HasMany
    {
        return $this->hasMany(FarmOperation::class, 'parcel_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    public function hasGeometry(): bool
    {
        return $this->geometry_wkt !== null && $this->geometry_wkt !== '';
    }

    public function areaUnit(): ?AreaUnit
    {
        return $this->area_unit === null ? null : AreaUnit::tryFrom($this->area_unit);
    }

    public function areaSource(): ?AreaSource
    {
        return $this->area_source === null ? null : AreaSource::tryFrom($this->area_source);
    }
}
