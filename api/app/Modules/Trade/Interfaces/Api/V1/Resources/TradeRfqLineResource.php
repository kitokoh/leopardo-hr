<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Resources;

use App\Modules\Trade\Domain\Models\TradeRfqLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ligne de RFQ — TRADE-003/#8478.
 *
 * `trade_product_id` peut etre null (saisie libre) : l'API expose donc AUSSI la
 * description, qui est dans tous les cas la source de verite a l'affichage.
 *
 * @mixin TradeRfqLine
 */
final class TradeRfqLineResource extends JsonResource
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
            'specs' => $this->specs,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'target_unit_price_minor' => $this->target_unit_price_minor,
            'position' => $this->position,
        ];
    }
}
