<?php

declare(strict_types=1);

namespace App\Modules\Communication\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communication\Domain\Models\CommunicationMessage;
use App\Modules\Communication\Infrastructure\Jobs\ClassifyCommunicationMessageJob;
use App\Modules\Communication\Interfaces\Api\V1\Controllers\Concerns\AssertsTenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Re-classification MANUELLE d'un message (BC-29 COMMUNICATION, R3 #7688).
 *
 * Reservee au PROPRIETAIRE de la boite (`CommunicationMessagePolicy` —
 * meme principal/rh = 403, cross-tenant = 404 via binding tenant-scope).
 * Le traitement part sur la queue `communication` (`force = true`) : la
 * reponse est un 202 avec l'etat courant, le resultat arrive de maniere
 * asynchrone (pipeline identique a la classification a la sync).
 */
class CommunicationMessageClassificationController extends Controller
{
    use AssertsTenantScope;

    public function classify(Request $request, CommunicationMessage $message): JsonResponse
    {
        // Binding implicite resolu avant le middleware tenant : garde 404
        // explicite (un message d'un autre tenant n'existe pas pour l'appelant).
        $this->assertTenantScope($request, $message);
        $this->authorize('classify', $message);

        ClassifyCommunicationMessageJob::dispatch(
            (string) $message->company_id,
            (string) $message->id,
            true,
        );

        return new JsonResponse([
            'data' => [
                'message_id' => $message->id,
                'classification_status' => $message->classification_status,
                'queued' => true,
            ],
        ], 202);
    }
}
