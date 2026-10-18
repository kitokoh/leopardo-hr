<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TradeQuoteLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne d'offre commerciale — BC-36 TRADE, TRADE-004/#8479.
 *
 * `line_total_minor` est CALCULE cote serveur (quantite × prix unitaire) : le
 * client ne fournit jamais un total, il ne peut donc pas imposer un prix
 * incoherent avec ses propres lignes.
 *
 * @property int $id
 * @property string $company_id
 * @property int $trade_quote_id
 * @property int|null $trade_product_id
 * @property string $description
 * @property string $quantity
 * @property string $unit
 * @property int $unit_price_minor
 * @property string|null $moq_quantity
 * @property int|null $lead_time_days
 * @property int $line_total_minor
 * @property int $position
 */
final class TradeQuoteLine extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TradeQuoteLineFactory> */
    use HasFactory;

    protected $table = 'trade_quote_lines';

    protected $fillable = [
        'trade_quote_id',
        'trade_product_id',
        'description',
        'quantity',
        'unit',
        'unit_price_minor',
        'moq_quantity',
        'lead_time_days',
        'line_total_minor',
        'position',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'trade_quote_id' => 'integer',
            'trade_product_id' => 'integer',
            'unit_price_minor' => 'integer',
            'lead_time_days' => 'integer',
            'line_total_minor' => 'integer',
            'position' => 'integer',
        ];
    }

    /** @return BelongsTo<TradeQuote, $this> */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(TradeQuote::class, 'trade_quote_id');
    }
}
