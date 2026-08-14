<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Http\Controllers\Controller;
use App\Modules\Payroll\Domain\Models\SocialContribution;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Issue #1815 — Gestion des cotisations sociales nationales (interface admin).
 *
 * Réservé au platform_admin (guard `super_admin_api`, groupe /api/v1/admin).
 * Les lignes nationales (`company_id = null`) sont le référentiel légal par
 * pays ; les overrides par entreprise restent gérés côté tenant.
 *
 * Compatible #1813 : lignes créées `active` (référentiel officiel).
 */
class SocialContributionAdminController extends Controller
{
    public function __construct(private readonly PayrollCalculator $payrollCalculator) {}
    public function index(Request $request): JsonResponse
    {
        $this->assertPlatformAdmin($request);

        $query = SocialContribution::query()->whereNull('company_id');

        if ($request->filled('country_code')) {
            $query->forCountry(strtoupper((string) $request->string('country_code')));
        }
        if ($request->filled('type')) {
            $type = (string) $request->string('type');
            if (in_array($type, ['employee', 'employer'], true)) {
                $query->where('type', $type);
            }
        }

        return response()->json([
            'data' => $query->orderBy('country_code')->orderBy('code')->get()
                ->map(fn (SocialContribution $c): array => $this->serialize($c)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertPlatformAdmin($request);

        $validated = $this->validatePayload($request);

        $contribution = SocialContribution::create([
            'company_id' => null,
            'country_code' => strtoupper($validated['country_code']),
            'name' => $validated['name'],
            'code' => $validated['code'],
            'type' => $validated['type'],
            'rate' => $validated['rate'],
            'cap' => $validated['cap'] ?? null,
            'effective_from' => $validated['effective_from'],
            'effective_to' => $validated['effective_to'] ?? null,
            'status' => SocialContribution::STATUS_ACTIVE,
        ]);

        return response()->json(['data' => $this->serialize($contribution)], 201);
    }

    public function update(Request $request, int $socialContribution): JsonResponse
    {
        $this->assertPlatformAdmin($request);

        /** @var SocialContribution $contribution */
        $contribution = SocialContribution::query()->whereNull('company_id')->findOrFail($socialContribution);
        $contribution->update($this->validatePayload($request, partial: true));

        return response()->json(['data' => $this->serialize($contribution->refresh())]);
    }

    public function destroy(Request $request, int $socialContribution): JsonResponse
    {
        $this->assertPlatformAdmin($request);

        /** @var SocialContribution $contribution */
        $contribution = SocialContribution::query()->whereNull('company_id')->findOrFail($socialContribution);
        $contribution->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array{country_code: string, name: string, code: string, type: string, rate: float, cap?: float|null, effective_from: string, effective_to?: string|null}
     */
    private function validatePayload(Request $request, bool $partial = false): array
    {
        $rules = [
            // #1951 : contrat partagé du moteur (plus de liste in: hardcodée).
            'country_code' => ['required', 'string', 'size:2', Rule::in($this->payrollCalculator->rulesResolver()->supportedCountryCodes())],
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:50'],
            'type' => ['required', Rule::in(['employee', 'employer'])],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'cap' => ['nullable', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
        ];

        if ($partial) {
            $rules = array_map(static fn ($rule): array => ['sometimes', ...(array) $rule], $rules);
        }

        $validated = $request->validate($rules);

        /** @var array{country_code: string, name: string, code: string, type: string, rate: float, cap?: float|null, effective_from: string, effective_to?: string|null} $validated */
        return $validated;
    }

    private function assertPlatformAdmin(Request $request): void
    {
        if (! $request->user() instanceof SuperAdmin) {
            abort(403, __('errors.FORBIDDEN'));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(SocialContribution $contribution): array
    {
        /** @var Carbon|null $effectiveTo */
        $effectiveTo = $contribution->effective_to;

        return [
            'id' => $contribution->id,
            'company_id' => $contribution->company_id,
            'country_code' => $contribution->country_code,
            'name' => $contribution->name,
            'code' => $contribution->code,
            'type' => $contribution->type,
            'rate' => $contribution->rate,
            'cap' => $contribution->cap,
            'effective_from' => $contribution->effective_from->toDateString(),
            'effective_to' => $effectiveTo?->toDateString(),
            'status' => $contribution->status,
        ];
    }
}
