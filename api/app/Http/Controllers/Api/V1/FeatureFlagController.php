<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Employee;
use App\Http\Controllers\Controller;
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
        $validated = $request->validate([
            'feature_key' => 'required|string|max:50',
            'plan' => 'required|in:trial,starter,business,enterprise',
            'enabled' => 'required|boolean',
            'limit_value' => 'nullable|integer|min:0',
        ]);

        $entry = FeaturePlanMatrix::updateOrCreate(
            ['feature_key' => $validated['feature_key'], 'plan' => $validated['plan']],
            ['enabled' => $validated['enabled'], 'limit_value' => $validated['limit_value'] ?? null],
        );

        return response()->json(['data' => $entry]);
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
