<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * #5803 — Rapport de rapprochement stock par station/période (FUEL-009).
 *
 * @property int $id
 * @property string $company_id
 * @property int $station_id
 * @property string $period
 * @property float $opening_quantity
 * @property float $delivered_quantity
 * @property float $sold_quantity
 * @property float $expected_level
 * @property float|null $actual_level
 * @property float $variance_liters
 * @property string $status
 * @property array<mixed>|null $data
 * @property int|null $reconciled_by
 * @property \Illuminate\Support\Carbon|null $reconciled_at
 *
 * @mixin Builder<static>
 */
class FuelStockReconciliation extends Model
{
    use BelongsToCompany;

    public const STATUS_OK = 'ok';

    public const STATUS_VARIANCE = 'variance';

    public const STATUS_INSUFFICIENT_DATA = 'insufficient_data';

    protected $table = 'fuel_stock_reconciliations';

    protected $fillable = [
        'company_id',
        'station_id',
        'period',
        'opening_quantity',
        'delivered_quantity',
        'sold_quantity',
        'expected_level',
        'actual_level',
        'variance_liters',
        'status',
        'data',
        'reconciled_by',
        'reconciled_at',
    ];

    protected $casts = [
        'opening_quantity' => 'float',
        'delivered_quantity' => 'float',
        'sold_quantity' => 'float',
        'expected_level' => 'float',
        'actual_level' => 'float',
        'variance_liters' => 'float',
        'data' => 'array',
        'reconciled_at' => 'datetime',
    ];
}
