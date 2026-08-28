<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Compteur (register) d'une pompe — FUEL-003.
 *
 * Un seul compteur ACTIF par (pompe, meter_code) : le retrait passe par
 * un second register (historique conservé). Types : mechanical,
 * electronic, main_totalizer, secondary_totalizer, test.
 *
 * @property int $id
 * @property string $company_id
 * @property int $station_id
 * @property int $pump_id
 * @property string $meter_code
 * @property string $meter_type
 * @property string|null $product_code
 * @property string $unit_code
 * @property int $precision_scale
 * @property int|null $rollover_limit
 * @property Carbon|null $installed_at
 * @property Carbon|null $retired_at
 * @property string $status  active|retired
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelMeterRegister extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_meter_registers';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_RETIRED = 'retired';

    public const TYPE_MECHANICAL = 'mechanical';

    public const TYPE_ELECTRONIC = 'electronic';

    public const TYPE_MAIN_TOTALIZER = 'main_totalizer';

    public const TYPE_SECONDARY_TOTALIZER = 'secondary_totalizer';

    public const TYPE_TEST = 'test';

    public const TYPES = [
        self::TYPE_MECHANICAL,
        self::TYPE_ELECTRONIC,
        self::TYPE_MAIN_TOTALIZER,
        self::TYPE_SECONDARY_TOTALIZER,
        self::TYPE_TEST,
    ];

    protected $fillable = [
        'company_id',
        'station_id',
        'pump_id',
        'meter_code',
        'meter_type',
        'product_code',
        'unit_code',
        'precision_scale',
        'rollover_limit',
        'installed_at',
        'retired_at',
        'status',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'precision_scale' => 'integer',
            'rollover_limit' => 'integer',
            'installed_at' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }
}
