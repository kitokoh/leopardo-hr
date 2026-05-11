<?php

namespace App\Services;

use App\Models\FeaturePlanMatrix;
use App\Models\Subscription;
use Illuminate\Support\Facades\Cache;

class FeatureService
{
    public function active(string $featureKey, string $companyId): bool
    {
        $plan = $this->getCompanyPlan($companyId);

        return Cache::remember(
            "feature:{$featureKey}:{$plan}",
            now()->addMinutes(10),
            function () use ($featureKey, $plan): bool {
                $entry = FeaturePlanMatrix::where('feature_key', $featureKey)
                    ->where('plan', $plan)
                    ->first();

                return $entry ? $entry->enabled : false;
            }
        );
    }

    public function limit(string $featureKey, string $companyId): ?int
    {
        $plan = $this->getCompanyPlan($companyId);

        return Cache::remember(
            "feature_limit:{$featureKey}:{$plan}",
            now()->addMinutes(10),
            function () use ($featureKey, $plan): ?int {
                $entry = FeaturePlanMatrix::where('feature_key', $featureKey)
                    ->where('plan', $plan)
                    ->first();

                return $entry ? $entry->limit_value : null;
            }
        );
    }

    private function getCompanyPlan(string $companyId): string
    {
        return Cache::remember(
            "company_plan:{$companyId}",
            now()->addMinutes(5),
            function () use ($companyId): string {
                $sub = Subscription::where('company_id', $companyId)
                    ->whereIn('status', ['active', 'trial'])
                    ->latest()
                    ->first();

                return $sub ? $sub->plan : 'trial';
            }
        );
    }
}
