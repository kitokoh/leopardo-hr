<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HospitalityManager\Interfaces\Api\V1\Traits\ChecksHospitalitySolution;
use Illuminate\Http\JsonResponse;

/**
 * État du module HospitalityManager pour le tenant courant — HOSP-001 (#7943).
 *
 * Sonde d'activation : la réponse n'arrive ici que si le feature flag
 * `hospitality` est actif pour la company résolue (fail-closed via
 * `assertSolutionActive()`). Sert de point de vérité aux clients (admin
 * plateforme, espace client) avant l'arrivée des lots HOSP-002→008
 * (spec SOLUTION_HOSPITALITY.md).
 */
class HospitalityModuleStatusController extends Controller
{
    use ChecksHospitalitySolution;

    public function show(): JsonResponse
    {
        $this->assertSolutionActive();

        return new JsonResponse([
            'data' => [
                'module' => 'hospitality',
                'enabled' => true,
                'status' => 'active',
                // Jalon livré du programme HOSP-001→008 (spec SOLUTION_HOSPITALITY.md).
                'stage' => 'HOSP-003',
                'capabilities' => [
                    'properties' => true,    // HOSP-002 (#7944) — référentiel établissements livré
                    'inventory' => true,     // HOSP-002 (#7944) — types de chambres & unités livrés
                    'team' => true,          // HOSP-003 (#7945) — équipe par établissement + RBAC scopé livrés
                    'reservations' => false, // HOSP-004 (#7946) — réservations & disponibilité
                    'rentals' => false,      // HOSP-005 (#7947) — baux & loyers
                    'public_api' => false,   // HOSP-006 (#7948) — vitrine publique API
                ],
            ],
        ]);
    }
}
