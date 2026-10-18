<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Models;

use App\Modules\Trade\Domain\Enums\TradeOrderDirection;
use App\Modules\Trade\Domain\Enums\TradeOrderStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TradeOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Commande Trade — BC-36 TRADE, TRADE-004/#8479.
 *
 * Issue de l'acceptation d'une offre (`trade_quote_id`). `direction`
 * `purchase|sale` : le meme objet porte la commande d'achat de l'importateur
 * et la commande de vente de l'exportateur.
 *
 * `total_minor` est FIGE cote serveur. Cycle verrouille par
 * {@see \App\Modules\Trade\Domain\Support\TradeOrderStateMachine} —
 * l'annulation n'est possible qu'AVANT expedition.
 *
 * @property int $id
 * @property string $company_id
 * @property string $reference
 * @property TradeOrderDirection $direction
 * @property int|null $trade_deal_id
 * @property int|null $trade_quote_id
 * @property int|null $trade_counterparty_id
 * @property TradeOrderStatus $status
 * @property string $currency
 * @property string|null $incoterm
 * @property int $total_minor
 * @property string|null $payment_terms
 * @property Carbon|null $ordered_at
 * @property Carbon|null $expected_delivery_at
 * @property Carbon|null $shipped_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
final class TradeOrder extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TradeOrderFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'trade_orders';

    protected $fillable = [
        'reference',
        'direction',
        'trade_deal_id',
        'trade_quote_id',
        'trade_counterparty_id',
        'status',
        'currency',
        'incoterm',
        'total_minor',
        'payment_terms',
        'ordered_at',
        'expected_delivery_at',
        'shipped_at',
        'delivered_at',
        'closed_at',
        'cancelled_at',
        'cancel_reason',
        'notes',
        'created_by_employee_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'direction' => TradeOrderDirection::class,
            'status' => TradeOrderStatus::class,
            'trade_deal_id' => 'integer',
            'trade_quote_id' => 'integer',
            'trade_counterparty_id' => 'integer',
            'total_minor' => 'integer',
            'ordered_at' => 'datetime',
            'expected_delivery_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'created_by_employee_id' => 'integer',
        ];
    }

    /** @return HasMany<TradeShipment, $this> */
    public function shipments(): HasMany
    {
        return $this->hasMany(TradeShipment::class, 'trade_order_id');
    }

    /** @return BelongsTo<TradeQuote, $this> */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(TradeQuote::class, 'trade_quote_id');
    }
}
