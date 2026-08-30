<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Application\Actions\SubmitTravelContactAction;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelContactRequest;
use Illuminate\Http\JsonResponse;

/**
 * TRAVEL-416 (#6068) — Formulaire de contact → lead CRM.
 *
 * Capte une demande (nom, email/tél, message borné, consentement) et
 * publie l'événement `travel.contact.submitted.v1` via l'outbox. Le BC
 * CRM (BC-11) consomme l'événement pour créer le lead — jamais d'écriture
 * directe dans les tables CRM depuis la verticale (spec §8.5).
 *
 * Le consentement email est également enregistré dans le registre
 * `travel_customer_contacts` (TRAVEL-415/#6067) : la soumission du
 * formulaire avec consentement vaut opt-in email explicite.
 *
 * La logique est partagée avec la route publique signée
 * `POST /travel/public/contact` (TRAVEL-913/#6425) via
 * `SubmitTravelContactAction`.
 */
class TravelContactController extends Controller
{
    public function __construct(private readonly SubmitTravelContactAction $submit) {}

    public function store(StoreTravelContactRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $this->submit->execute((string) $actor->company_id, $request->validated());

        return response()->json(['status' => 'received'], 202);
    }
}
