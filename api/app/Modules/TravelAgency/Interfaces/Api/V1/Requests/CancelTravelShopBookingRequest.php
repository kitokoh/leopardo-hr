<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Annulation en ligne d'une réservation (portail client, TRAVEL-702 #6089).
 *
 * Le `code` (code de validation d'un billet de la réservation) prouve la
 * possession du billet ; le `reason` (motif) est obligatoire et conservé
 * (audit). L'annulation est bornée par le statut (pending/confirmed) et la
 * date de départ — le contrat métier reste `CancelBookingAction`.
 */
class CancelTravelShopBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'min:6', 'max:32'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }
}
