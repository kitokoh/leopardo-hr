<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Application\Actions\SubmitTravelContactAction;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelContactRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-913 (#6425) — Formulaire de contact PUBLIC.
 *
 * Route publique signée (`signed` + throttle), le `company_id` est un
 * paramètre signé de l'URL (pattern `restaurant/public/*`, RESTO-805) :
 * forger un lien pour un AUTRE tenant est impossible. Délègue à
 * `SubmitTravelContactAction` (même flux que `POST /travel/contact` :
 * consentement email obligatoire, événement outbox lead CRM).
 */
class TravelPublicContactController extends Controller
{
    public function __construct(private readonly SubmitTravelContactAction $submit) {}

    public function store(StoreTravelContactRequest $request): JsonResponse
    {
        $companyId = (string) $request->query('company');

        $this->submit->execute($companyId, $request->validated());

        return response()->json(['status' => 'received'], 202);
    }
}
