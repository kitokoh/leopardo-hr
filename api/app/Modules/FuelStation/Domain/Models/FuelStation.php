<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Station-service (entité légale, issue #5796).
 *
 * @property string $id
 * @property string $company_id
 * @property string $code
 * @property string $name
 * @property string|null $address
 * @property string|null $city
 * @property string|null $country
 * @property string $timezone
 * @property string $status
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelStation extends Model
{
    use BelongsToCompany;
    use HasUuids;

    protected $table = 'fuel_stations';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'archived_at' => 'datetime',
        ];
    }

    /** @return HasMany<FuelSite, $this> */
    public function sites(): HasMany
    {
        return $this->hasMany(FuelSite::class, 'station_id');
    }
}
