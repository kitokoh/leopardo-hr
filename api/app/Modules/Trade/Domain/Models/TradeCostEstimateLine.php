<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Models;

use App\Modules\Trade\Domain\Enums\TradeCostLineType;
use App\Modules\Trade\Domain\Enums\TradeCostSource;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TradeCostEstimateLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Poste de cout d'un calcul rendu — BC-36 TRADE, TRADE-007/#8482.
 *
 * `source` est OBLIGATOIRE et structure l'honnetete du calcul : un poste
 * `unavailable` est affiche comme non chiffre et EXCLU du total, jamais
 * remplace par une estimation inventee.
 *
 * @property int $id
 * @property string $company_id
 * @property int $trade_cost_estimate_id
 * @property TradeCostLineType $type
 * @property string|null $label
 * @property int $amount_minor
 * @property string $currency
 * @property int|null $converted_amount_minor
 * @property TradeCostSource $source
 * @property string|null $note
 * @property int $position
 */
final class TradeCostEstimateLine extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TradeCostEstimateLineFactory> */
    use HasFactory;

    protected $table = 'trade_cost_estimate_lines';

    protected $fillable = [
        'trade_cost_estimate_id',
        'type',
        'label',
        'amount_minor',
        'currency',
        'converted_amount_minor',
        'source',
        'note',
        'position',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'trade_cost_estimate_id' => 'integer',
            'type' => TradeCostLineType::class,
            'amount_minor' => 'integer',
            'converted_amount_minor' => 'integer',
            'source' => TradeCostSource::class,
            'position' => 'integer',
        ];
    }

    /** @return BelongsTo<TradeCostEstimate, $this> */
    public function estimate(): BelongsTo
    {
        return $this->belongsTo(TradeCostEstimate::class, 'trade_cost_estimate_id');
    }
}
