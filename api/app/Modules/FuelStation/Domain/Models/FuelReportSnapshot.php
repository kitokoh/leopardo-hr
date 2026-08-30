<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Read model de reporting FuelStation (FUEL-017, issue #5811).
 *
 * Instantané pré-agrégé par (station, type, période). Le payload évite les
 * jointures profondes au dashboard ; la contrainte unique rend le recalcul
 * idempotent (rejouer remplace le snapshot, jamais de doublon).
 *
 * @property int $id
 * @property string $company_id
 * @property int $station_id
 * @property string $snapshot_type
 * @property string $period_start
 * @property string $period_end
 * @property array<string, mixed> $payload
 * @property int|null $generated_by
 * @property Carbon $generated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelReportSnapshot extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_report_snapshots';

    public const TYPE_PUMP_VOLUMES = 'pump_volumes';

    public const TYPE_SALES = 'sales';

    public const TYPE_SHIFTS = 'shifts';

    public const TYPE_VARIANCES = 'variances';

    public const TYPE_STOCK = 'stock';

    public const TYPE_STATION_PERFORMANCE = 'station_performance';

    public const TYPES = [
        self::TYPE_PUMP_VOLUMES,
        self::TYPE_SALES,
        self::TYPE_SHIFTS,
        self::TYPE_VARIANCES,
        self::TYPE_STOCK,
        self::TYPE_STATION_PERFORMANCE,
    ];

    protected $fillable = [
        'company_id',
        'station_id',
        'snapshot_type',
        'period_start',
        'period_end',
        'payload',
        'generated_by',
        'generated_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'station_id' => 'integer',
            'period_start' => 'date:Y-m-d',
            'period_end' => 'date:Y-m-d',
            'payload' => 'array',
            'generated_at' => 'datetime',
        ];
    }
}
