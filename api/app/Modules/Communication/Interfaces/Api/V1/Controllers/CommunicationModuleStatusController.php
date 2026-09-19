<?php

declare(strict_types=1);

namespace App\Modules\Communication\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communication\Domain\Support\CommunicationFeatures;
use Illuminate\Http\JsonResponse;

/**
 * État du module Communication pour le tenant courant (BC-29, R0 #7685).
 *
 * Endpoint santé/état du squelette : la route est gardée par
 * `module.communication` (feature flag tenant `communication`) — si la
 * réponse arrive ici, le module est actif pour la company résolue.
 * Sert de sonde d'activation aux clients (admin plateforme, espace
 * client) avant l'arrivée des lots R1→R6.
 */
class CommunicationModuleStatusController extends Controller
{
    public function show(): JsonResponse
    {
        return new JsonResponse([
            'data' => [
                'module' => CommunicationFeatures::COMMUNICATION,
                'enabled' => true,
                'status' => 'active',
                // Jalon livré du programme R0→R6 (spec MODULE_COMMUNICATION_EMAIL_IA.md).
                'stage' => 'R2',
                'capabilities' => [
                    'integrations' => true,  // R1 (#7686) — OAuth Google serveur livré
                    'sync' => true,          // R2 (#7687) — sync Gmail incrémentale livrée
                    'classification' => false, // R3 — classification IA + CRM
                    'follow_ups' => false,   // R4 — relances automatiques
                    'replies' => false,      // R5 — réponses draft/confirm/auto
                ],
            ],
        ]);
    }
}
