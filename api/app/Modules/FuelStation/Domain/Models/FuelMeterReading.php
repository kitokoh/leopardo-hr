<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Relevé cumulé d'un compteur FuelStation (issue #5798).
 *
 * @property string $id
 * @property string $company_id
 * @property string $meter_id
 * @property string|null $pump_id
 * @property string|null $site_id
 * @property string|null $station_id
 * @property string|null $operator_id
 * @property string|null $shift_id
 * @property float $reading_value
 * @property Carbon $reading_at
 * @property string|null $reading_at_local
 * @property float|null $delta
 * @property bool $rollover
 * @property bool $anomaly
 * @property string $source
 * @property string|null $note
 * @property string|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelMeterReading extends Model
{
    use BelongsToCompany;
    use HasUuids;

    protected $table = 'fuel_meter_readings';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'reading_value' => 'float',
            'reading_at' => 'datetime',
            'delta' => 'float',
            'rollover' => 'boolean',
            'anomaly' => 'boolean',
        ];
    }
}
