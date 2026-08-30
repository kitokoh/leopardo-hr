<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * #5803 — Mouvement de stock d'une cuve (FUEL-009).
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $station_id
 * @property int $tank_id
 * @property string $type
 * @property float $quantity
 * @property float|null $unit_price
 * @property \Illuminate\Support\Carbon $occurred_at
 * @property string|null $reference
 * @property string|null $notes
 * @property int|null $created_by
 * @property string $idempotency_key
 *
 * @mixin Builder<static>
 */
class FuelStockMovement extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_stock_movements';

    protected $fillable = [
        'company_id',
        'station_id',
        'tank_id',
        'type',
        'quantity',
        'unit_price',
        'occurred_at',
        'reference',
        'notes',
        'created_by',
        'idempotency_key',
    ];

    protected $casts = [
        'quantity' => 'float',
        'unit_price' => 'float',
        'occurred_at' => 'datetime',
    ];

    public function tank(): BelongsTo
    {
        return $this->belongsTo(FuelTank::class, 'tank_id');
    }
}
