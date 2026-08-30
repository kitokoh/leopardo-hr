<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Entrée de stock FuelStation (livraison, retour, ajustement motivé)
 * — FUEL-009 (issue #5803).
 *
 * `idempotency_key` UNIQUE par tenant → rejeu zéro doublon. Un ajustement
 * (entry_type = adjustment) exige un `reason` non vide (aucun ajustement
 * silencieux — vérifié par FuelStockService ET contrainte application).
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $station_id
 * @property string $product_code
 * @property float $quantity
 * @property float $unit_cost
 * @property string $entry_type delivery|adjustment|return
 * @property string|null $reason
 * @property string|null $reference
 * @property Carbon $entry_date
 * @property string $idempotency_key
 * @property int|null $created_by
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelStockEntry extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_stock_entries';

    public const ENTRY_DELIVERY = 'delivery';

    public const ENTRY_ADJUSTMENT = 'adjustment';

    public const ENTRY_RETURN = 'return';

    public const ENTRY_TYPES = [self::ENTRY_DELIVERY, self::ENTRY_ADJUSTMENT, self::ENTRY_RETURN];

    protected $fillable = [
        'company_id',
        'station_id',
        'product_code',
        'quantity',
        'unit_cost',
        'entry_type',
        'reason',
        'reference',
        'entry_date',
        'idempotency_key',
        'created_by',
        'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'station_id' => 'integer',
            'quantity' => 'float',
            'unit_cost' => 'float',
            'entry_date' => 'date',
            'created_by' => 'integer',
        ];
    }
}
