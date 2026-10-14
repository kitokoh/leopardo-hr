<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Interfaces\Api\V1\Resources;

use App\Modules\Vtc\Domain\Models\VtcRide;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Suivi public d'une course marketplace VTC (#8423, M-14) — DTO STRICT
 * minimal : référence, statut du cycle de vie, ETA approximatif (durée
 * estimée persistée à la réservation) et devis du passager.
 *
 * JAMAIS exposés : identifiants internes, PII passager (nom/téléphone),
 * position ou identité du chauffeur (pas de localisation précise —
 * anti-pistage), metadata, journal d'événements, horodatages internes de
 * transition. La preuve de possession (référence + code de suivi) borne
 * déjà la lecture à cette seule course.
 *
 * @mixin VtcRide
 */
final class VtcMarketplaceRideStatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference,
            'status' => $this->status->value,
            'estimated_duration_s' => $this->estimated_duration_s,
            'estimated_price_minor' => $this->estimated_price_minor,
            'currency' => $this->currency,
            'pickup_address' => $this->pickup_address,
            'dropoff_address' => $this->dropoff_address,
            'requested_at' => $this->requested_at?->toIso8601String(),
        ];
    }
}
