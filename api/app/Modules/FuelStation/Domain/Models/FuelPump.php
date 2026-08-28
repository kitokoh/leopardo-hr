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
 * @property string $id
 * @property string $company_id
 * @property string $site_id
 * @property string|null $equipment_id
 * @property string $code
 * @property string|null $name
 * @property string $status
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelPump extends Model
{
    use BelongsToCompany;
    use HasUuids;

    protected $table = 'fuel_pumps';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    /** @return HasMany<FuelMeter, $this> */
    public function meters(): HasMany
    {
        return $this->hasMany(FuelMeter::class, 'pump_id');
    }
}
