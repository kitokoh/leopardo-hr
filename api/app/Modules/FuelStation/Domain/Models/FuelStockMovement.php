<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Mouvement de stock FuelStation — FUEL-009 (issue #5803).
 *
 * Journal append-only : livraison (+), vente (−), ajustement (±).
 * Quantités en unités mineures entières signées (jamais de flottant métier).
 * `idempotency_key` UNIQUE (company_id, idempotency_key) → rejeu sans doublon.
 *
 * @property int $id
 * @property string $company_id
 * @property int $station_id
 * @property int|null $tank_id
 * @property string $product_type
 * @property string $type delivery|sale|adjustment|transfer
 * @property int $quantity_minor
 * @property string|null $reason
 * @property string|null $reference
 * @property string|null $idempotency_key
 * @property int|null $recorded_by
 * @property Carbon $recorded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelStockMovement extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_stock_movements';

    public const TYPE_DELIVERY = 'delivery';

    public const TYPE_SALE = 'sale';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPE_TRANSFER = 'transfer';

    public const TYPES = [
        self::TYPE_DELIVERY,
        self::TYPE_SALE,
        self::TYPE_ADJUSTMENT,
        self::TYPE_TRANSFER,
    ];

    protected $fillable = [
        'company_id',
        'station_id',
        'tank_id',
        'product_type',
        'type',
        'quantity_minor',
        'reason',
        'reference',
        'idempotency_key',
        'recorded_by',
        'recorded_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity_minor' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }
}
