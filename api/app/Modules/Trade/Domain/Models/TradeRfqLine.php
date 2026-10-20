<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TradeRfqLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne de RFQ — BC-36 TRADE, TRADE-003/#8478.
 *
 * `trade_product_id` est NULLABLE : la saisie LIBRE d'une ligne est permise.
 * Un acheteur qui cherche un produit qu'il n'a pas encore catalogue ne doit
 * jamais etre bloque par l'obligation de creer une fiche produit d'abord.
 *
 * @property int $id
 * @property string $company_id
 * @property int $trade_rfq_id
 * @property int|null $trade_product_id
 * @property string $description
 * @property array<string, mixed>|null $specs
 * @property string $quantity
 * @property string $unit
 * @property int|null $target_unit_price_minor
 * @property int $position
 */
final class TradeRfqLine extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TradeRfqLineFactory> */
    use HasFactory;

    protected $table = 'trade_rfq_lines';

    protected $fillable = [
        'trade_rfq_id',
        'trade_product_id',
        'description',
        'specs',
        'quantity',
        'unit',
        'target_unit_price_minor',
        'position',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'trade_rfq_id' => 'integer',
            'trade_product_id' => 'integer',
            'specs' => 'array',
            'target_unit_price_minor' => 'integer',
            'position' => 'integer',
        ];
    }

    /** @return BelongsTo<TradeRfq, $this> */
    public function rfq(): BelongsTo
    {
        return $this->belongsTo(TradeRfq::class, 'trade_rfq_id');
    }

    /** @return BelongsTo<TradeProduct, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(TradeProduct::class, 'trade_product_id');
    }
}
