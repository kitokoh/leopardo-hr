<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Models;

use App\Modules\Trade\Domain\Enums\TradeTransportMode;
use App\Modules\Trade\Domain\Enums\TradeTransportRequestStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TradeTransportRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Demande de cotation transport — BC-36 TRADE, TRADE-006/#8481.
 *
 * Emise par l'acheteur ou le vendeur, diffusee aux prestataires
 * (transporteurs, transitaires) qui repondent par une proposition STRUCTUREE
 * (`trade_transport_quotes`). C'est le point d'entree de l'espace prestataire,
 * concu des le depart comme extensible (role `carrier` / `forwarder` sur les
 * contreparties).
 *
 * @property int $id
 * @property string $company_id
 * @property string $reference
 * @property int|null $trade_deal_id
 * @property int|null $trade_order_id
 * @property string|null $origin_country
 * @property string|null $origin_city
 * @property string|null $origin_port
 * @property string|null $destination_country
 * @property string|null $destination_city
 * @property string|null $destination_port
 * @property TradeTransportMode $mode
 * @property string|null $cargo_description
 * @property string|null $weight_kg
 * @property string|null $volume_m3
 * @property TradeTransportRequestStatus $status
 */
final class TradeTransportRequest extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TradeTransportRequestFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'trade_transport_requests';

    protected $fillable = [
        'reference',
        'trade_deal_id',
        'trade_order_id',
        'origin_country',
        'origin_city',
        'origin_port',
        'destination_country',
        'destination_city',
        'destination_port',
        'mode',
        'cargo_description',
        'weight_kg',
        'volume_m3',
        'containers',
        'incoterm',
        'pickup_date',
        'status',
        'created_by_employee_id',
        'sent_at',
        'closed_at',
        'selected_quote_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'trade_deal_id' => 'integer',
            'trade_order_id' => 'integer',
            'mode' => TradeTransportMode::class,
            'containers' => 'array',
            'pickup_date' => 'date',
            'status' => TradeTransportRequestStatus::class,
            'created_by_employee_id' => 'integer',
            'sent_at' => 'datetime',
            'closed_at' => 'datetime',
            'selected_quote_id' => 'integer',
        ];
    }

    /** @return HasMany<TradeTransportQuote, $this> */
    public function quotes(): HasMany
    {
        return $this->hasMany(TradeTransportQuote::class, 'trade_transport_request_id');
    }
}
