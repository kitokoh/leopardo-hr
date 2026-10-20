<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Application\Actions\SubmitPublicContactRequestAction;
use App\Modules\Platform\Interfaces\Api\V1\Requests\StorePublicContactRequest;
use Illuminate\Http\JsonResponse;

/**
 * #8760 — Contact public « Contacter ce professionnel » (passerelle Leopardo Public).
 *
 * `POST /api/v1/public/contact-requests` — SANS auth, SANS middleware tenant
 * (patron BC-27/BC-28 : routes publiques isolées, `throttle:shop-public`).
 *
 * Le tenant destinataire n'est JAMAIS lu depuis la requête : il est résolu
 * côté serveur par (`target_type`, `target_slug`) via les
 * `PublicProfileResolver` de chaque verticale, fail-closed. Un client ne peut
 * donc pas router une demande vers une entreprise arbitraire en changeant un
 * identifiant — il ne fournit qu'un slug public déjà visible dans `/decouvrir`.
 *
 * Réponses :
 *  - `201` — demande créée ;
 *  - `200` — rejeu idempotent (même `idempotency_key`), aucun doublon ;
 *  - `404` — profil inexistant OU non publiable (réponse UNIFORME :
 *    anti-énumération) ;
 *  - `422` — payload invalide (dont `target_type` non résoluble).
 *
 * ⚠️ Un 404 ici ne dit jamais « ce professionnel existe mais est suspendu » :
 * les deux cas sont indistinguables depuis l'extérieur, ce qui est le
 * comportement attendu par l'exigence « un profil suspendu doit disparaître ».
 */
class PublicContactRequestController extends Controller
{
    public function __construct(private readonly SubmitPublicContactRequestAction $action) {}

    public function store(StorePublicContactRequest $request): JsonResponse
    {
        // Honeypot anti-spam : un bot qui remplit le champ caché reçoit un
        // accusé factice — rien n'est persisté et rien n'est notifié.
        if ($request->filled('company_website')) {
            return new JsonResponse(['data' => ['status' => 'received']], 201);
        }

        $result = $this->action->execute($request->validated(), $request->ip());

        if ($result === null) {
            // Uniforme : profil inexistant, dépublié, suspendu ou verticale
            // inactive → même réponse.
            return new JsonResponse(['error' => 'PROFILE_NOT_AVAILABLE'], 404);
        }

        $contactRequest = $result['request'];

        return new JsonResponse([
            'data' => [
                'status' => 'received',
                // Référence publique opaque : ne révèle ni tenant, ni id interne
                // utilisable pour énumérer quoi que ce soit.
                'reference' => (string) $contactRequest->idempotency_key,
            ],
        ], $result['created'] ? 201 : 200);
    }
}
