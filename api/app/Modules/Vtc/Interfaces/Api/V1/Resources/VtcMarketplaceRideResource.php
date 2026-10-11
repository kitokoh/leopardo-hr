<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Interfaces\Api\V1\Resources;

use App\Modules\Vtc\Domain\Models\VtcRide;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Course VTC créée via la marketplace publique (#8423, M-13) — DTO
 * STRICT : référence publique, statut et devis persisté (minor units +
 * devise), adresses telles que fournies par le passager.
 *
 * JAMAIS exposés : identifiants internes (id, company_id, driver_id,
 * fare_profile_id), PII passager au-delà de ce que le client vient de
 * soumettre (nom/téléphone non répercutés — le client les connaît),
 * metadata, journal d'événements, position chauffeur. Le code de suivi
 * (`tracking_code`) n'est pas dans la Resource : il est ajouté par le
 * contrôleur à la CRÉATION uniquement (present-once BOS-050 — jamais au
 * rejeu idempotent).
 *
 * @mixin VtcRide
 */
final class VtcMarketplaceRideResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference,
            'status' => $this->status->value,
            'pickup_address' => $this->pickup_address,
            'dropoff_address' => $this->dropoff_address,
            'estimated_duration_s' => $this->estimated_duration_s,
            'estimated_price_minor' => $this->estimated_price_minor,
            'currency' => $this->currency,
            'requested_at' => $this->requested_at?->toIso8601String(),
        ];
    }
}
