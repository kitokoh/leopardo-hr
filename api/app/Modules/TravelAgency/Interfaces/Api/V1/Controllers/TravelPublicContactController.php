<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Application\Actions\SubmitTravelContactAction;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelContactRequest;
use Illuminate\Http\JsonResponse;

/**
 * TRAVEL-913 (#6425) — Formulaire de contact PUBLIC.
 *
 * Route sans authentification : le visiteur n'a pas de session tenant, le
 * tenant est résolu depuis `{companySlug}` (pattern `public/careers/*`,
 * `PublicCareerController`) au lieu de l'utilisateur Sanctum. Même
 * validation (`StoreTravelContactRequest`) et même logique
 * (`SubmitTravelContactAction`) que `POST /travel/contact` — consentement
 * obligatoire, aucun envoi sans opt-in explicite.
 */
class TravelPublicContactController extends Controller
{
    public function __construct(private readonly SubmitTravelContactAction $submit) {}

    public function store(StoreTravelContactRequest $request, string $companySlug): JsonResponse
    {
        $company = Company::query()
            ->where('slug', $companySlug)
            ->where('status', '!=', 'suspended')
            ->firstOrFail();

        $this->submit->execute($company->id, $request->validated());

        return response()->json(['status' => 'received'], 202);
    }
}
