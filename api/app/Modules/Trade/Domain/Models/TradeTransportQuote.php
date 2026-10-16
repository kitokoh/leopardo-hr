<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Models;

use App\Modules\Trade\Domain\Enums\TradeTransportQuoteStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TradeTransportQuoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Reponse d'un prestataire a une demande de cotation transport — BC-36 TRADE,
 * TRADE-006/#8481.
 *
 * `included_fees` / `excluded_fees` rendent le PERIMETRE DE COUT explicite.
 * C'est la condition d'une comparaison honnete (§7) : deux offres dont les
 * perimetres different ne sont jamais presentees comme equivalentes, meme si
 * leur prix total se ressemble.
 *
 * @property int $id
 * @property string $company_id
 * @property int $trade_transport_request_id
 * @property int|null $trade_counterparty_id
 * @property int $price_minor
 * @property string $currency
 * @property list<string>|null $included_fees
 * @property list<string>|null $excluded_fees
 * @property int|null $transit_time_days
 * @property Carbon|null $validity_until
 * @property TradeTransportQuoteStatus $status
 */
final class TradeTransportQuote extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TradeTransportQuoteFactory> */
    use HasFactory;

    protected $table = 'trade_transport_quotes';

    protected $fillable = [
        'trade_transport_request_id',
        'trade_counterparty_id',
        'price_minor',
        'currency',
        'included_fees',
        'excluded_fees',
        'transit_time_days',
        'validity_until',
        'status',
        'notes',
        'received_at',
        'decided_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'trade_transport_request_id' => 'integer',
            'trade_counterparty_id' => 'integer',
            'price_minor' => 'integer',
            'included_fees' => 'array',
            'excluded_fees' => 'array',
            'transit_time_days' => 'integer',
            'validity_until' => 'date',
            'status' => TradeTransportQuoteStatus::class,
            'received_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TradeTransportRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(TradeTransportRequest::class, 'trade_transport_request_id');
    }
}
