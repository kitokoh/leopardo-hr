<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Snapshot de rapprochement de stock FuelStation — FUEL-009 (issue #5803).
 *
 * Un snapshot par (station, produit, jour) — UNIQUE → le job de rapprochement
 * est rejouable sans doublon (upsert). Un écart n'est jamais silencieux :
 * `status = variance` + `notes` explicatives (jamais d'ajustement automatique).
 *
 * @property int $id
 * @property string $company_id
 * @property int $station_id
 * @property string $product_type
 * @property string $day
 * @property int $opening_minor
 * @property int $deliveries_minor
 * @property int $sales_minor
 * @property int $adjustments_minor
 * @property int $expected_closing_minor
 * @property int|null $metered_delta_minor
 * @property int|null $variance_minor
 * @property string $status balanced|variance
 * @property string|null $notes
 * @property Carbon $computed_at
 *
 * @mixin Builder<static>
 */
class FuelStockReconciliation extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_stock_reconciliations';

    public const STATUS_BALANCED = 'balanced';

    public const STATUS_VARIANCE = 'variance';

    protected $fillable = [
        'company_id',
        'station_id',
        'product_type',
        'day',
        'opening_minor',
        'deliveries_minor',
        'sales_minor',
        'adjustments_minor',
        'expected_closing_minor',
        'metered_delta_minor',
        'variance_minor',
        'status',
        'notes',
        'computed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'opening_minor' => 'integer',
            'deliveries_minor' => 'integer',
            'sales_minor' => 'integer',
            'adjustments_minor' => 'integer',
            'expected_closing_minor' => 'integer',
            'metered_delta_minor' => 'integer',
            'variance_minor' => 'integer',
            'day' => 'date',
            'computed_at' => 'datetime',
        ];
    }
}
