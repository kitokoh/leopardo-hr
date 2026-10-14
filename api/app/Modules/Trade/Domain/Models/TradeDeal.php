<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Models;

use App\Modules\Trade\Domain\Enums\TradeDealDirection;
use App\Modules\Trade\Domain\Enums\TradeDealStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TradeDealFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Dossier de transaction Trade (BC-36 TRADE, TRADE-005/#8480).
 *
 * Conteneur metier : rassemble RFQ, offres, commandes, transport, couts,
 * documents et messages d'une meme affaire. Porte la timeline (`trade_events`)
 * et la conversation. Le SENS commercial (`direction`) est porte par le
 * dossier — jamais par un role utilisateur.
 *
 * Isolation tenant : trait BelongsToCompany (fail-closed, anti-spoof).
 *
 * @property int $id
 * @property string $company_id
 * @property string $reference
 * @property TradeDealDirection $direction
 * @property int|null $trade_counterparty_id
 * @property string $title
 * @property TradeDealStatus $status
 * @property string $currency
 * @property int|null $opened_by_employee_id
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $closed_at
 */
final class TradeDeal extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TradeDealFactory> */
    use HasFactory;

    protected $table = 'trade_deals';

    protected $fillable = [
        'reference',
        'direction',
        'trade_counterparty_id',
        'title',
        'status',
        'currency',
        'opened_by_employee_id',
        'metadata',
        'closed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'direction' => TradeDealDirection::class,
            'status' => TradeDealStatus::class,
            'trade_counterparty_id' => 'integer',
            'opened_by_employee_id' => 'integer',
            'metadata' => 'array',
            'closed_at' => 'datetime',
        ];
    }

    /** @return HasMany<TradeRfq, $this> */
    public function rfqs(): HasMany
    {
        return $this->hasMany(TradeRfq::class, 'trade_deal_id');
    }

    /** @return HasMany<TradeQuote, $this> */
    public function quotes(): HasMany
    {
        return $this->hasMany(TradeQuote::class, 'trade_deal_id');
    }

    /** @return HasMany<TradeOrder, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(TradeOrder::class, 'trade_deal_id');
    }

    /** @return HasMany<TradeMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(TradeMessage::class, 'trade_deal_id');
    }

    /** @return HasMany<TradeDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(TradeDocument::class, 'trade_deal_id');
    }
}
