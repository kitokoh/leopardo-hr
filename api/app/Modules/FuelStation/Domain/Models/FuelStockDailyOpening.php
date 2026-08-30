<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Niveau de cuve en début de journée commerciale (FUEL-009, issue #5803).
 *
 * Figé au PREMIER mouvement du jour (vente ou livraison) : l'ouverture est
 * une valeur INDÉPENDANTE du niveau courant au moment du rapprochement —
 * sans elle, l'écart attendu vs mesuré serait toujours nul (circulaire) et
 * la détection de vol/fuite impossible.
 *
 * @property int $id
 * @property string $company_id
 * @property int $tank_id
 * @property string $open_date
 * @property int $opening_level_minor
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelStockDailyOpening extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_stock_daily_openings';

    protected $fillable = [
        'company_id',
        'tank_id',
        'open_date',
        'opening_level_minor',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tank_id' => 'integer',
            'open_date' => 'date:Y-m-d',
            'opening_level_minor' => 'integer',
        ];
    }
}
