<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\CRM\Application\Services\CrmDashboardReadModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Issue #5721 — Dashboard CRM : pipeline & qualité des données.
 *
 * Read models agrégés tenant-scoped (company_id de l'acteur), autorisés par
 * la Policy `crm.dashboard`. Aucun tri/SQL fourni par le client — les deux
 * endpoints sont en lecture seule et bornés (agrégats SQL uniques).
 */
class CrmDashboardController extends Controller
{
    public function __construct(private readonly CrmDashboardReadModel $dashboardReadModel) {}

    public function pipeline(Request $request): JsonResponse
    {
        $this->authorize('crm.dashboard');

        /** @var Employee $actor */
        $actor = $request->user();

        return response()->json([
            'data' => $this->dashboardReadModel->pipeline((string) $actor->company_id),
        ]);
    }

    public function quality(Request $request): JsonResponse
    {
        $this->authorize('crm.dashboard');

        /** @var Employee $actor */
        $actor = $request->user();

        return response()->json([
            'data' => $this->dashboardReadModel->quality((string) $actor->company_id),
        ]);
    }
}
