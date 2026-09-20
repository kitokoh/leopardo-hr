<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Pharmacy\Application\Services\PharmacyDashboardService;
use App\Modules\Pharmacy\Interfaces\Api\V1\Traits\ChecksPharmacySolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tableau de bord fondateur — PHARMA-007 (#7804).
 *
 * KPIs tenant-scopés (ventes hors voided, stock, top produits, achats,
 * conformité), montants en décimal string. RBAC manager (pilotage).
 */
class PharmacyDashboardController extends Controller
{
    use ChecksPharmacySolution;

    public function __construct(private readonly PharmacyDashboardService $dashboard) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if (! $actor->isManager()) {
            abort(403);
        }

        return response()->json([
            'data' => $this->dashboard->snapshot((string) $actor->company_id),
        ]);
    }
}
