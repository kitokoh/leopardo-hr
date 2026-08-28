<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Modules\FuelStation\Domain\Enums\FuelStationStatus;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Station-service d'un tenant — Issue #5796 (FUEL-002).
 *
 * @property int $id
 * @property string|null $company_id
 * @property string $code
 * @property string $name
 * @property string|null $address
 * @property string|null $phone
 * @property string $status
 * @property string $timezone
 * @property string|null $currency
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, FuelSite> $sites
 *
 * @mixin Builder<static>
 */
class FuelStation extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_stations';

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'address',
        'phone',
        'status',
        'timezone',
        'currency',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'encrypted:array',
    ];

    /**
     * @return HasMany<FuelSite, $this>
     */
    public function sites(): HasMany
    {
        return $this->hasMany(FuelSite::class, 'station_id');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', FuelStationStatus::Active->value);
    }
}
