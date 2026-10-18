<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Resources;

use App\Modules\Trade\Domain\Models\TradeCostEstimateLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Poste de cout — TRADE-007/#8482.
 *
 * `source` est exposee au meme titre que le montant : l'interface peut ainsi
 * distinguer visuellement un poste confirme d'une estimation, d'une saisie ou
 * d'un poste indisponible (jamais chiffre).
 *
 * @mixin TradeCostEstimateLine
 */
final class TradeCostEstimateLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'label' => $this->label ?? $this->type->label(),
            'amount_minor' => $this->amount_minor,
            'currency' => $this->currency,
            'converted_amount_minor' => $this->converted_amount_minor,
            'source' => $this->source->value,
            'counts_toward_total' => $this->source->countsTowardTotal(),
            'note' => $this->note,
            'position' => $this->position,
        ];
    }
}
