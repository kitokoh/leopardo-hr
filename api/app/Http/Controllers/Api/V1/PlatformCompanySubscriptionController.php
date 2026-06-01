<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PlatformCompanySubscriptionController extends Controller
{
    public function show(string $companyId): JsonResponse
    {
        DB::statement('SET search_path TO public');

        $company = Company::query()->findOrFail($companyId);

        return new JsonResponse([
            'data' => $this->payload($company),
        ]);
    }

    public function update(Request $request, string $companyId): JsonResponse
    {
        DB::statement('SET search_path TO public');

        $company = Company::query()->findOrFail($companyId);

        $validated = $request->validate([
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')],
            'status' => ['required', Rule::in(['active', 'trial', 'suspended', 'expired'])],
            'subscription_start' => ['nullable', 'date'],
            'subscription_end' => ['nullable', 'date', 'after_or_equal:subscription_start'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $company->fill([
            'plan_id' => $validated['plan_id'],
            'status' => $validated['status'],
            'subscription_start' => $validated['subscription_start'] ?? null,
            'subscription_end' => $validated['subscription_end'] ?? null,
            'notes' => $validated['notes'] ?? null,
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
