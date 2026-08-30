<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Niveau de stock observé d'une cuve (FUEL-009, issue #5803).
 *
 * Vérité physique par cuve et par jour : le rapprochement compare ce
 * niveau au stock attendu (ouverture + livraisons − ventes) et expose
 * l'écart — jamais d'ajustement silencieux. Rejeu idempotent par
 * `idempotency_key` (UNIQUE par tenant).
 *
 * @property int $id
 * @property string $company_id
 * @property int $tank_id
 * @property Carbon $recorded_on format Y-m-d
 * @property int $level_minor unités mineures (centilitres)
 * @property string $source_code manual|delivery|calculated
 * @property string|null $idempotency_key
 * @property string|null $notes
 * @property int|null $recorded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelTankStockLevel extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_tank_stock_levels';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_DELIVERY = 'delivery';

    public const SOURCE_CALCULATED = 'calculated';

    public const SOURCES = [self::SOURCE_MANUAL, self::SOURCE_DELIVERY, self::SOURCE_CALCULATED];

    protected $fillable = [
        'company_id',
        'tank_id',
        'recorded_on',
        'level_minor',
        'source_code',
        'idempotency_key',
        'notes',
        'recorded_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tank_id' => 'integer',
            'level_minor' => 'integer',
            'recorded_on' => 'date',
        ];
    }
}
