<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Resources;

use App\Modules\Trade\Domain\Models\TradeQuoteLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ligne d'offre — TRADE-004/#8479.
 *
 * `line_total_minor` est le total CALCULE par le serveur : c'est la valeur de
 * reference affichable, jamais un montant fourni par le client.
 *
 * @mixin TradeQuoteLine
 */
final class TradeQuoteLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trade_product_id' => $this->trade_product_id,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'unit_price_minor' => $this->unit_price_minor,
            'moq_quantity' => $this->moq_quantity,
            'lead_time_days' => $this->lead_time_days,
            'line_total_minor' => $this->line_total_minor,
            'position' => $this->position,
        ];
    }
}
