<?php

declare(strict_types=1);

namespace App\Modules\Billing\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Support\PlatformCompanyLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PlatformCompanySubscriptionController extends Controller
{
    public function show(string $companyId): JsonResponse
    {
        $company = PlatformCompanyLookup::findOrFail($companyId);

        return new JsonResponse([
            'data' => $this->payload($company),
        ]);
    }

    public function update(Request $request, string $companyId): JsonResponse
    {
        $company = PlatformCompanyLookup::findOrFail($companyId);

        $validated = $request->validate([
            'plan_id' => ['sometimes', 'integer', Rule::exists('plans', 'id')],
            'status' => ['sometimes', Rule::in(['active', 'trial', 'suspended', 'expired'])],
            'subscription_start' => ['nullable', 'date'],
            'subscription_end' => ['nullable', 'date', 'after_or_equal:subscription_start'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // #7474 — PATCH partiel : une clé absente du payload conserve la valeur
        // existante (repli sur $company->…), seul un `null` envoyé explicitement
        // efface le champ. `$request->has()` renvoie true même pour une clé
        // présente à null, ce qui distingue « absent » de « effacement »
        // (même convention que PlatformCompanyFeatureController, cf. #7432).
        $company->fill([
            'plan_id' => $validated['plan_id'] ?? $company->plan_id,
            'status' => $validated['status'] ?? $company->status,
            'subscription_start' => $request->has('subscription_start') ? $validated['subscription_start'] : $company->subscription_start,
            'subscription_end' => $request->has('subscription_end') ? $validated['subscription_end'] : $company->subscription_end,
            'notes' => $request->has('notes') ? $validated['notes'] : $company->notes,
        ]);
        $company->save();
        $company->refresh();

        return new JsonResponse([
            'data' => $this->payload($company),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Company $company): array
    {
        $plan = DB::table('plans')->where('id', $company->plan_id)->first();

        return [
            'company_id' => $company->id,
            'status' => $company->status,
            'plan' => [
                'id' => $company->plan_id,
                'name' => $plan->name ?? null,
                'price_monthly' => isset($plan->price_monthly) ? (float) $plan->price_monthly : null,
                'price_yearly' => isset($plan->price_yearly) ? (float) $plan->price_yearly : null,
                'max_employees' => isset($plan->max_employees) ? (int) $plan->max_employees : null,
            ],
            'subscription_start' => $company->subscription_start,
            'subscription_end' => $company->subscription_end,
            'currency' => $company->currency,
            'notes' => $company->notes,
        ];
    }
}
