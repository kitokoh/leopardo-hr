<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Resources;

use App\Modules\TravelAgency\Domain\Enums\DocumentType;
use App\Modules\TravelAgency\Domain\Models\TravelPassenger;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * TRAVEL-312..316 — Représentation API d'un passager.
 *
 * RGPD : le n° de pièce d'identité (chiffré + hash) n'est JAMAIS exposé —
 * seule l'existence d'un document est indiquée. Interne au module
 * (PA2-ARCH-010).
 *
 * @mixin TravelPassenger
 */
class TravelPassengerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // birth_date / document_type sont nullable en base (cast modèle) :
        // on préserve le rendu historique ('Y-m-d' / null) malgré les types
        // @property déclarés non-nullables sur le modèle.
        /** @var Carbon|string|null $birthDate */
        $birthDate = $this->birth_date;
        /** @var DocumentType|null $documentType */
        $documentType = $this->document_type;

        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'full_name' => $this->full_name,
            'birth_date' => $birthDate instanceof Carbon ? $birthDate->toDateString() : $birthDate,
            'document_type' => $documentType?->value,
            'has_document' => ! empty($this->document_number_encrypted),
            'age_category' => $this->age_category->value,
            'class_id' => $this->class_id,
            'seat_number' => $this->seat_number,
            'unit_price_minor' => $this->unit_price_minor,
        ];
    }
}
