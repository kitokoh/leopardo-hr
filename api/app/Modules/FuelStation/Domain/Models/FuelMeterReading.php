<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Relevé de compteur (append-only) — FUEL-004 (spec §13.2).
 *
 * Une correction crée une NOUVELLE version (source_code='correction') et
 * marque l'original 'corrected' — jamais de suppression.
 *
 * @property int $id
 * @property string $company_id
 * @property int $station_id
 * @property int $pump_id
 * @property int $meter_id
 * @property int $reading_value_minor
 * @property string $reading_unit
 * @property Carbon $captured_at_utc
 * @property Carbon $captured_at_station_local
 * @property string $timezone
 * @property int|null $captured_by_employee_id
 * @property int|null $shift_id
 * @property string $source_code  operator|import|device|correction
 * @property string|null $device_reference
 * @property string $idempotency_key
 * @property string $status  submitted|accepted|rejected|corrected
 * @property string|null $correction_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelMeterReading extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_meter_readings';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CORRECTED = 'corrected';

    public const SOURCE_OPERATOR = 'operator';

    public const SOURCE_IMPORT = 'import';

    public const SOURCE_DEVICE = 'device';

    public const SOURCE_CORRECTION = 'correction';

    protected $fillable = [
        'company_id',
        'station_id',
        'pump_id',
        'meter_id',
        'reading_value_minor',
        'reading_unit',
        'captured_at_utc',
        'captured_at_station_local',
        'timezone',
        'captured_by_employee_id',
        'shift_id',
        'source_code',
        'device_reference',
        'idempotency_key',
        'status',
        'correction_reason',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'reading_value_minor' => 'integer',
            'captured_at_utc' => 'datetime',
            'captured_at_station_local' => 'datetime',
            'shift_id' => 'integer',
        ];
    }
}
