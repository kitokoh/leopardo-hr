<?php

declare(strict_types=1);

namespace App\Modules\Billing\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Billing\Domain\Models\FeaturePlanMatrix;
use App\Modules\Billing\Domain\Services\EntitlementGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeatureFlagController extends Controller
{
    public function __construct(private readonly EntitlementGuard $entitlementGuard) {}

    public function matrix(Request $request): JsonResponse
    {
        $matrix = FeaturePlanMatrix::orderBy('feature_key')
            ->orderBy('plan')
            ->get()
            ->groupBy('feature_key')
            ->map(function ($items) {
                return $items->pluck('enabled', 'plan');
            });

        return response()->json(['data' => $matrix]);
    }

    public function check(Request $request, string $featureKey): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        // DEP-BC21 #6247 : la lecture d'entitlement est centralisée dans
        // EntitlementGuard (plan actif × matrice, fail-closed) — aucune
        // logique d'entitlement dupliquée dans les contrôleurs. `company_id`
        // est garanti par le middleware tenant (fail-closed si absent).
        $companyId = (string) $user->company_id;
        $plan = $this->entitlementGuard->planForCompany($companyId);
        $enabled = $this->entitlementGuard->isFeatureEnabled($companyId, $featureKey);
        $limit = $this->entitlementGuard->featureLimit($companyId, $featureKey);

        return response()->json([
            'data' => [
                'feature' => $featureKey,
                'plan' => $plan,
                'enabled' => $enabled,
                'limit' => $limit,
            ],
        ]);
    }
}
