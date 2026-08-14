<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Http\Controllers\Controller;
use App\Modules\Payroll\Domain\Models\TaxSlab;
use App\Modules\Payroll\Infrastructure\Services\CountryRulesResolver;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Issue #1814 — Gestion des barèmes fiscaux nationaux (interface admin).
 *
 * Réservé au platform_admin (guard `super_admin_api`, groupe /api/v1/admin).
 * Les lignes nationales (`company_id = null`) sont le référentiel légal par
 * pays ; les overrides par entreprise restent gérés côté tenant (#1813).
 *
 * Compatible avec #1813 : les lignes sont créées `active` (référentiel
 * officiel), le workflow de validation s'applique aux propositions tenant.
 */
class TaxSlabAdminController extends Controller
{
    public function __construct(private readonly PayrollCalculator $payrollCalculator) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertPlatformAdmin($request);

        $query = TaxSlab::query()->whereNull('company_id');

        if ($request->filled('country_code')) {
            $query->forCountry(strtoupper((string) $request->string('country_code')));
        }

        return response()->json([
            'data' => $query->orderBy('country_code')->orderBy('min_amount')->get()
                ->map(fn (TaxSlab $slab): array => $this->serialize($slab)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertPlatformAdmin($request);

        $validated = $this->validatePayload($request);

        $slab = TaxSlab::create([
            'company_id' => null,
            'country_code' => strtoupper($validated['country_code']),
            'name' => $validated['name'],
            'min_amount' => $validated['min_amount'],
            'max_amount' => $validated['max_amount'] ?? null,
            'rate' => $validated['rate'],
            'fixed_deduction' => (float) ($validated['fixed_deduction'] ?? 0),
            'effective_from' => $validated['effective_from'],
            'effective_to' => $validated['effective_to'] ?? null,
            'status' => TaxSlab::STATUS_ACTIVE,
        ]);

        return response()->json(['data' => $this->serialize($slab)], 201);
    }

    public function update(Request $request, int $taxSlab): JsonResponse
    {
        $this->assertPlatformAdmin($request);

        /** @var TaxSlab $slab */
        $slab = TaxSlab::query()->whereNull('company_id')->findOrFail($taxSlab);

        $validated = $this->validatePayload($request, partial: true);
        $slab->update($validated);

        return response()->json(['data' => $this->serialize($slab->refresh())]);
    }

    public function destroy(Request $request, int $taxSlab): JsonResponse
    {
        $this->assertPlatformAdmin($request);

        /** @var TaxSlab $slab */
        $slab = TaxSlab::query()->whereNull('company_id')->findOrFail($taxSlab);
        $slab->delete();

        return response()->json(null, 204);
    }

    /**
     * Réinitialise les tranches nationales d'un pays avec les valeurs légales
     * par défaut du moteur (`defaultTaxSlabs`), après confirmation.
     */
    public function resetDefaults(Request $request): JsonResponse
    {
        $this->assertPlatformAdmin($request);

        $countryCode = strtoupper((string) $request->string('country_code'));
        $this->assertCountry($countryCode);

        // Supprime les lignes nationales existantes du pays (référentiel).
        TaxSlab::query()->whereNull('company_id')->where('country_code', $countryCode)->delete();

        // Après suppression, taxSlabs() retombe sur les défauts légaux du moteur.
        $defaults = $this->payrollCalculator->getRules($countryCode)->taxSlabs();

        $created = 0;
        foreach ($defaults as $slab) {
            TaxSlab::create([
                'company_id' => null,
                'country_code' => $countryCode,
                'name' => __('payroll.tax_scale_default_name', ['country' => $countryCode, 'year' => (string) now()->year]),
                'min_amount' => $slab['min'],
                'max_amount' => $slab['max'],
                'rate' => $slab['rate'],
                'fixed_deduction' => $slab['fixed_deduction'],
                'effective_from' => now()->startOfYear()->toDateString(),
                'effective_to' => null,
                'status' => TaxSlab::STATUS_ACTIVE,
            ]);
            $created++;
        }

        return response()->json(['data' => ['country_code' => $countryCode, 'created' => $created]]);
    }

    /**
     * @return array{country_code: string, name: string, min_amount: float, max_amount: float|null, rate: float, fixed_deduction?: float, effective_from: string, effective_to?: string|null}
     */
    private function validatePayload(Request $request, bool $partial = false): array
    {
        $rules = [
            'country_code' => ['required', 'string', 'size:2', Rule::in((new CountryRulesResolver)->supportedCountryCodes())],
            'name' => ['required', 'string', 'max:150'],
            'min_amount' => ['required', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'fixed_deduction' => ['nullable', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
        ];

        if ($partial) {
            $rules = array_map(static fn ($rule): array => ['sometimes', ...(array) $rule], $rules);
        }

        $validated = $request->validate($rules);

        /** @var array{country_code: string, name: string, min_amount: float, max_amount: float|null, rate: float, fixed_deduction: float, effective_from: string, effective_to: string|null} $validated */
        return $validated;
    }

    private function assertCountry(string $countryCode): void
    {
        if (! (new CountryRulesResolver)->supports($countryCode)) {
            abort(422, __('payroll.rate_country_unsupported'));
        }
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
    private function serialize(TaxSlab $slab): array
    {
        /** @var Carbon|null $effectiveTo */
        $effectiveTo = $slab->effective_to;

        return [
            'id' => $slab->id,
            'company_id' => $slab->company_id,
            'country_code' => $slab->country_code,
            'name' => $slab->name,
            'min_amount' => $slab->min_amount,
            'max_amount' => $slab->max_amount,
            'rate' => $slab->rate,
            'fixed_deduction' => $slab->fixed_deduction,
            'effective_from' => $slab->effective_from->toDateString(),
            'effective_to' => $effectiveTo?->toDateString(),
            'status' => $slab->status,
        ];
    }
}
