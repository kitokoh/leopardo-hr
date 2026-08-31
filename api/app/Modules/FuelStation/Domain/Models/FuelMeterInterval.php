<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Intervalle entre deux relevés consécutifs d'un même compteur — FUEL-004.
 *
 * `delta_minor` peut être négatif UNIQUEMENT en anomalie (jamais accepté
 * silencieusement). `calculation_status` : valid | rollover | anomaly |
 * pending_review.
 *
 * @property int $id
 * @property string $company_id
 * @property int $meter_id
 * @property int $previous_reading_id
 * @property int $current_reading_id
 * @property int $previous_value_minor
 * @property int $current_value_minor
 * @property int $delta_minor
 * @property int $interval_seconds
 * @property Carbon $calculated_at
 * @property string $calculation_status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelMeterInterval extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_meter_intervals';

    public const STATUS_VALID = 'valid';

    public const STATUS_ROLLOVER = 'rollover';

    public const STATUS_ANOMALY = 'anomaly';

    public const STATUS_PENDING_REVIEW = 'pending_review';

    protected $fillable = [
        'company_id',
        'meter_id',
        'previous_reading_id',
        'current_reading_id',
        'previous_value_minor',
        'current_value_minor',
        'delta_minor',
        'interval_seconds',
        'calculated_at',
        'calculation_status',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'delta_minor' => 'integer',
            'interval_seconds' => 'integer',
            'calculated_at' => 'datetime',
        ];
    }
}
