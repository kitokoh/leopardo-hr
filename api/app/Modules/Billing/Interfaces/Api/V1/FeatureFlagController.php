<?php

declare(strict_types=1);

namespace App\Modules\Billing\Interfaces\Api\V1;

use App\Http\Controllers\Controller;
use App\Core\Auth\Domain\Models\Employee;
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
        // logique d'entitlement dupliquée dans les contrôleurs.
        $plan = $this->entitlementGuard->planForCompany($user->company_id);
        $enabled = $this->entitlementGuard->isFeatureEnabled($user->company_id, $featureKey);
        $limit = $this->entitlementGuard->featureLimit($user->company_id, $featureKey);

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

