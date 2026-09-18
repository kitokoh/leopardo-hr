<?php

declare(strict_types=1);

namespace App\Core\Tenant\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Infrastructure\Services\ResourceTypeRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Issue #7598 (R1 de l'épique #7597) — `GET /v1/resources/{type}`.
 *
 * Le sélecteur du responsable : « quels restaurants / véhicules / caméras puis-je
 * donner ? ». C'est ce qui manquait pour qu'une assignation soit possible sans
 * connaître les identifiants par cœur.
 *
 * Deux garde-fous :
 *  - un type non déclaré au registre est refusé (`RESOURCE_TYPE_UNKNOWN`) —
 *    on n'ouvre jamais un type par défaut ;
 *  - la liste est **filtrée par ce que l'acteur peut voir** du type
 *    (`accessibleResourceIds`) : `null` = aucune restriction (principal, ou
 *    type pas encore assigné), liste vide = rien, liste = les ressources
 *    assignées au niveau demandé.
 *
 * Réservé au principal du tenant en R1 : c'est le geste d'assignation qui
 * ouvre ce catalogue (R4 élargira l'UX).
 */
class ResourceCatalogController extends Controller
{
    public function __construct(private readonly ResourceTypeRegistry $registry) {}

    public function index(Request $request, string $type): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $actor->isPrincipal()) {
            return new JsonResponse([
                'error' => 'RESOURCE_ACCESS_DENIED',
                'message' => __('errors.RESOURCE_ACCESS_DENIED'),
            ], 403);
        }

        if (! $this->registry->has($type)) {
            throw new NotFoundHttpException(__('errors.RESOURCE_TYPE_UNKNOWN'));
        }

        $only = $actor->accessibleResourceIds($type);

        return new JsonResponse([
            'data' => $this->registry->listForCompany($type, $actor->company_id, $only),
        ]);
    }
}
