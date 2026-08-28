<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Modules\FuelStation\Domain\Enums\FuelSiteStatus;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Site opérationnel d'une station — Issue #5796 (FUEL-002).
 *
 * La référence à la station est cross-tenant-impossible : FK composite
 * (station_id, company_id) → fuel_stations(id, company_id).
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $station_id
 * @property string $code
 * @property string $name
 * @property string|null $address
 * @property string $status
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read FuelStation|null $station
 *
 * @mixin Builder<static>
 */
class FuelSite extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_sites';

    protected $fillable = [
        'company_id',
        'station_id',
        'code',
        'name',
        'address',
        'status',
        'metadata',
    ];

    protected $casts = [
        'station_id' => 'integer',
        'metadata' => 'encrypted:array',
    ];

    /**
     * @return BelongsTo<FuelStation, $this>
     */
    public function station(): BelongsTo
    {
        return $this->belongsTo(FuelStation::class, 'station_id');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', FuelSiteStatus::Active->value);
    }
}
