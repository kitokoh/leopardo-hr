<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Livraison de carburant (entrée en stock) — FUEL-009, issue #5803.
 *
 * Référence fournisseur UNIQUE par tenant → rejeu idempotent (zéro
 * doublon). Cycle de vie draft → received|rejected ; la réception est
 * idempotente (un rejeu renvoie l'état inchangé). Quantités en unités
 * mineures entières (jamais de flottants métier).
 *
 * @property int $id
 * @property string $company_id
 * @property int $station_id
 * @property int|null $tank_id
 * @property string $product_code
 * @property string|null $supplier_name
 * @property int $quantity_minor
 * @property string $unit_code l|gal
 * @property Carbon $delivered_at
 * @property string $reference
 * @property string $status draft|received|rejected
 * @property int|null $received_by
 * @property Carbon|null $received_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelStockDelivery extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_stock_deliveries';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_RECEIVED, self::STATUS_REJECTED];

    protected $fillable = [
        'company_id',
        'station_id',
        'tank_id',
        'product_code',
        'supplier_name',
        'quantity_minor',
        'unit_code',
        'delivered_at',
        'reference',
        'status',
        'received_by',
        'received_at',
        'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'station_id' => 'integer',
            'tank_id' => 'integer',
            'quantity_minor' => 'integer',
            'delivered_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }
}
