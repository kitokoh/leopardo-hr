<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Mouvement de stock d'une station-service — FUEL-009 (#5803).
 *
 * Journal append-only des entrées/sorties. Règles :
 * - `direction` in|out, `reason` delivery|sale|adjustment|opening ;
 * - aucun ajustement silencieux : chaque mouvement est explicite,
 *   audité (created_by) et référencé (reference_type/reference_id) ;
 * - `idempotency_key` (nullable, unique par tenant) : les raisons
 *   `delivery` et `adjustment` sont rejouables sans doublon.
 *
 * @property int $id
 * @property string $company_id
 * @property int $station_id
 * @property int|null $tank_id
 * @property string $product_type
 * @property int $quantity_minor
 * @property string $direction in|out
 * @property string $reason delivery|sale|adjustment|opening
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property Carbon $movement_at
 * @property string|null $idempotency_key
 * @property int|null $created_by
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelStockMovement extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_stock_movements';

    public const DIRECTION_IN = 'in';

    public const DIRECTION_OUT = 'out';

    public const REASON_DELIVERY = 'delivery';

    public const REASON_SALE = 'sale';

    public const REASON_ADJUSTMENT = 'adjustment';

    public const REASON_OPENING = 'opening';

    public const REFERENCE_DELIVERY = 'delivery';

    public const REFERENCE_SALE = 'sale';

    public const REFERENCE_RECONCILIATION = 'reconciliation';

    protected $fillable = [
        'company_id',
        'station_id',
        'tank_id',
        'product_type',
        'quantity_minor',
        'direction',
        'reason',
        'reference_type',
        'reference_id',
        'movement_at',
        'idempotency_key',
        'created_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'station_id' => 'integer',
            'tank_id' => 'integer',
            'quantity_minor' => 'integer',
            'reference_id' => 'integer',
            'movement_at' => 'datetime',
            'created_by' => 'integer',
        ];
    }
}
