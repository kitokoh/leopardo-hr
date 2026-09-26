<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Application\Actions\SubmitTravelContactAction;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelContactRequest;
use Illuminate\Http\JsonResponse;

/**
 * TRAVEL-416 (#6068) — Formulaire de contact → lead CRM.
 *
 * `POST /travel/contact` : valide la soumission (email/tél, message borné,
 * consentement) puis délègue à `SubmitTravelContactAction` — le même flux
 * canonique que la route publique signée (TRAVEL-913) : upsert idempotent
 * du registre de consentement `travel_customer_contacts` (TRAVEL-415/#6067,
 * la soumission avec consentement vaut opt-in email explicite) puis
 * publication `travel.contact.submitted.v1` via l'outbox (jamais d'import
 * direct dans le BC CRM, règle D7). Réponse 202 (traitement asynchrone).
 *
 * Régression corrigée (#8128) : la fusion de dette 6ce743cee avait réduit
 * ce contrôleur à une publication d'événement SANS l'upsert du registre de
 * consentement — la route authentifiée court-circuitait le flux canonique.
 */
class TravelContactController extends Controller
{
    public function __construct(private readonly SubmitTravelContactAction $submit) {}

    public function store(StoreTravelContactRequest $request): JsonResponse
    {
        /** @var Company $company */
        $company = currentCompany();

        $this->submit->execute($company->id, $request->validated());

        return response()->json(['status' => 'received'], 202);
    }
}
