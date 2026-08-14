<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Http\Controllers\Controller;
use App\Modules\Payroll\Domain\Models\PayrollCalculationAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Issue #1874 — audit & observabilité des calculs de paie.
 *
 * Liste paginée des calculs (simulations + runs) d'une entreprise :
 * contexte pays, version/période des règles, brut, net, coût, impôt,
 * identifiant de corrélation, statut et horodatage. RBAC : manager = sa
 * société uniquement (isolation tenant) ; super-admin plateforme = tout
 * (filtre ?company_id optionnel). Jamais de données sensibles (la table
 * est whitelistée par construction).
 */
class PayrollCalculationAuditController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee|SuperAdmin $actor */
        $actor = $request->user();

        $query = PayrollCalculationAudit::query()->orderByDesc('created_at');

        if ($actor instanceof SuperAdmin) {
            $companyId = $request->query('company_id');
            if ($companyId !== null) {
                $query->where('company_id', (int) $companyId);
            }
        } else {
            // Isolation tenant : le manager ne voit que les calculs de SA
            // société (Policy viewAny déjà passée — défense en profondeur).
            $query->where('company_id', $actor->company_id);
        }

        $perPage = min((int) $request->query('per_page', 20), 100);

        return response()->json(['data' => $query->paginate($perPage)]);
    }
}
