<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TradeCostEstimateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Calcul du cout total rendu — BC-36 TRADE, TRADE-007/#8482.
 *
 * `total_minor` est produit par `TradeLandedCostCalculator` (service pur,
 * deterministe) a partir des lignes, et JAMAIS fige sur un total client.
 *
 * `fx_rate` / `fx_rate_date` / `fx_source` tracent la conversion : sans taux
 * connu, les conversions restent nulles et l'estimation le signale — le
 * systeme ne devine jamais un taux de change.
 *
 * @property int $id
 * @property string $company_id
 * @property string $reference
 * @property int|null $trade_deal_id
 * @property int|null $trade_quote_id
 * @property int|null $trade_order_id
 * @property string|null $origin_country
 * @property string|null $destination_country
 * @property string|null $incoterm
 * @property int $goods_value_minor
 * @property string $goods_currency
 * @property string $display_currency
 * @property string|null $fx_rate
 * @property Carbon|null $fx_rate_date
 * @property string|null $fx_source
 * @property int $total_minor
 */
final class TradeCostEstimate extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TradeCostEstimateFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'trade_cost_estimates';

    protected $fillable = [
        'reference',
        'trade_deal_id',
        'trade_quote_id',
        'trade_order_id',
        'origin_country',
        'destination_country',
        'incoterm',
        'goods_value_minor',
        'goods_currency',
        'display_currency',
        'fx_rate',
        'fx_rate_date',
        'fx_source',
        'total_minor',
        'status',
        'notes',
        'created_by_employee_id',
        'finalized_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'trade_deal_id' => 'integer',
            'trade_quote_id' => 'integer',
            'trade_order_id' => 'integer',
            'goods_value_minor' => 'integer',
            'fx_rate_date' => 'date',
            'total_minor' => 'integer',
            'created_by_employee_id' => 'integer',
            'finalized_at' => 'datetime',
        ];
    }

    /** @return HasMany<TradeCostEstimateLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(TradeCostEstimateLine::class, 'trade_cost_estimate_id')->orderBy('position');
    }
}
