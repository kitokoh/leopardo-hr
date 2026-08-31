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
        // Les colonnes sont nullables et `birth_date` est casté en date :
        // on rétablit la réalité runtime (l'annotation du modèle est plus
        // stricte que la base).
        /** @var Carbon|null $birthDate */
        $birthDate = $this->birth_date;
        /** @var DocumentType|null $documentType */
        $documentType = $this->document_type;
        /** @var string|null $documentNumber */
        $documentNumber = $this->document_number_encrypted;

        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'full_name' => $this->full_name,
            'birth_date' => $birthDate?->toDateString(),
            'document_type' => $documentType?->value,
            'has_document' => $documentNumber !== null,
            'age_category' => $this->age_category->value,
            'class_id' => $this->class_id,
            'seat_number' => $this->seat_number,
            'unit_price_minor' => $this->unit_price_minor,
        ];
    }
}
