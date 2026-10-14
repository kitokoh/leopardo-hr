<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Models;

use App\Modules\Trade\Domain\Enums\TradeShipmentStatus;
use App\Modules\Trade\Domain\Enums\TradeTransportMode;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TradeShipmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Expedition d'une commande — BC-36 TRADE, TRADE-004/#8479.
 *
 * `tracking_reference` est SAISIE (ou capturee d'une reponse prestataire) :
 * aucun adaptateur de tracking externe n'etant souscrit, le systeme n'invente
 * jamais une position, une etape ou un ETA. Ce qui est affiche vient de ce qui
 * a ete reellement saisi.
 *
 * @property int $id
 * @property string $company_id
 * @property int $trade_order_id
 * @property TradeTransportMode $mode
 * @property string|null $carrier_name
 * @property string|null $tracking_reference
 * @property TradeShipmentStatus $status
 * @property string|null $origin
 * @property string|null $destination
 * @property Carbon|null $eta
 */
final class TradeShipment extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TradeShipmentFactory> */
    use HasFactory;

    protected $table = 'trade_shipments';

    protected $fillable = [
        'trade_order_id',
        'mode',
        'carrier_name',
        'tracking_reference',
        'status',
        'origin',
        'destination',
        'eta',
        'shipped_at',
        'arrived_at',
        'delivered_at',
        'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'trade_order_id' => 'integer',
            'mode' => TradeTransportMode::class,
            'status' => TradeShipmentStatus::class,
            'eta' => 'datetime',
            'shipped_at' => 'datetime',
            'arrived_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TradeOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(TradeOrder::class, 'trade_order_id');
    }
}
