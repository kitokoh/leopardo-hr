<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\FeaturePlanMatrix;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeatureFlagController extends Controller
{
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
        $plan = $this->getCompanyPlan($user->company_id);

        $entry = FeaturePlanMatrix::where('feature_key', $featureKey)
            ->where('plan', $plan)
            ->first();

        $enabled = $entry ? $entry->enabled : false;
        $limit = $entry ? $entry->limit_value : null;

        return response()->json([
            'data' => [
                'feature' => $featureKey,
                'plan' => $plan,
                'enabled' => $enabled,
                'limit' => $limit,
            ],
        ]);
    }

    public function updateMatrix(Request $request): JsonResponse
    {
        abort(403, 'Feature plan matrix writes are reserved to platform administration.');
    }

    private function getCompanyPlan(string $companyId): string
    {
        $subscription = Subscription::where('company_id', $companyId)
            ->where('status', 'active')
            ->latest()
            ->first();

        return $subscription ? $subscription->plan : 'trial';
    }
}
