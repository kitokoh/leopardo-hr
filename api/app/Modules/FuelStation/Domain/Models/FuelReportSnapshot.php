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
 * Read model de reporting FuelStation — FUEL-017 (#5811).
 *
 * Snapshot horodaté (sans jointures profondes au moment de la lecture) :
 * recalcul idempotent par (company, station, type, date) — le réécrire le
 * même jour retourne le même payload, jamais de doublon.
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $station_id
 * @property string $report_type daily_volumes|shift_summary|sales_summary|stock_status|variance_summary
 * @property Carbon $snapshot_date
 * @property array<string, mixed> $payload
 * @property Carbon $computed_at
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
    public const TYPE_DAILY_VOLUMES = 'daily_volumes';

    public const TYPE_SHIFT_SUMMARY = 'shift_summary';

    public const TYPE_SALES_SUMMARY = 'sales_summary';

    public const TYPE_STOCK_STATUS = 'stock_status';

    public const TYPE_VARIANCE_SUMMARY = 'variance_summary';

    public const TYPES = [
        self::TYPE_DAILY_VOLUMES,
        self::TYPE_SHIFT_SUMMARY,
        self::TYPE_SALES_SUMMARY,
        self::TYPE_STOCK_STATUS,
        self::TYPE_VARIANCE_SUMMARY,
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
        'report_type',
        'snapshot_date',
        'payload',
        'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'station_id' => 'integer',
            'period_start' => 'date:Y-m-d',
            'period_end' => 'date:Y-m-d',
            'payload' => 'array',
            'generated_at' => 'datetime',
            'snapshot_date' => 'date',
            'payload' => 'array',
            'computed_at' => 'datetime',
        ];
    }
}
