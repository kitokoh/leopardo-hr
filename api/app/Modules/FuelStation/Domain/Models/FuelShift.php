<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Shift de travail d'une station-service (FUEL-005, issue #5799).
 *
 * Créneau horaire récurrent (ex. 06:00–14:00) rattaché à une station
 * (`station_id` bigint — FK composite (station_id, company_id) → fuel_stations)
 * et décliné par jour via {@see FuelShiftAssignment}.
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $station_id
 * @property string $name
 * @property string $start_time format H:i
 * @property string $end_time format H:i
 * @property string $status active|inactive
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelShift extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_shifts';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_INACTIVE];

    protected $fillable = [
        'company_id',
        'station_id',
        'name',
        'start_time',
        'end_time',
        'status',
        'notes',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'station_id' => 'integer',
            'start_time' => 'string',
            'end_time' => 'string',
        ];
    }

    /** @return HasMany<FuelShiftAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(FuelShiftAssignment::class, 'shift_id');
    }
}
