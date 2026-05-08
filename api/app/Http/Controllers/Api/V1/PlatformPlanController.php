<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PlatformPlanController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $plans = DB::table('plans')
            ->orderBy('price_monthly')
            ->orderBy('id')
            ->get()
            ->map(fn ($plan): array => [
                'id' => (int) $plan->id,
                'name' => $plan->name,
                'price_monthly' => (float) $plan->price_monthly,
                'price_yearly' => (float) $plan->price_yearly,
                'max_employees' => $plan->max_employees !== null ? (int) $plan->max_employees : null,
                'features' => $this->decodeFeatures($plan->features ?? null),
                'trial_days' => (int) $plan->trial_days,
                'is_active' => (bool) $plan->is_active,
            ])
            ->values();

        return new JsonResponse([
            'data' => [
                'items' => $plans,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeFeatures(mixed $features): array
    {
        if (is_array($features)) {
            return $features;
        }

        if (! is_string($features) || $features === '') {
            return [];
        }

        $decoded = json_decode($features, true);

        return is_array($decoded) ? $decoded : [];
    }
}
