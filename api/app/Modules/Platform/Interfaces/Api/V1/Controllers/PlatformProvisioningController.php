<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Infrastructure\Services\PlatformProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * #8626 / #8627 (Lot 6) — Console superadmin du provisionnement.
 *
 * Accès : `platform.permission:companies.view` (même permission que la liste
 * des entreprises — cette vue est une AUTRE LECTURE du même périmètre, pas
 * un privilège supplémentaire ; une permission dédiée aurait obligé à
 * redistribuer un rôle existant sans changer ce qu'il peut voir).
 *
 * Aucune donnée métier client n'est exposée : voir la note d'isolation dans
 * `PlatformProvisioningService`.
 */
class PlatformProvisioningController extends Controller
{
    public function __invoke(Request $request, PlatformProvisioningService $service): JsonResponse
    {
        // `?refresh=1` force un recalcul : l'écran « Actualiser » ne doit pas
        // resservir un état périmé.
        return new JsonResponse($service->overview(
            limit: $request->integer('limit', 50),
        ));
    }
}
