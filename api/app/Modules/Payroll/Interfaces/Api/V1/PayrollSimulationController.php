<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Http\Controllers\Controller;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\AbstractCountryRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRulesResolver;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Issue #1814 — Simulation d'impact d'un barème fiscal (dry-run).
 *
 * POST /api/v1/payroll/simulate (manager principal/comptable) et
 * POST /api/v1/admin/payroll/simulate (platform_admin).
 *
 * Ne persiste RIEN : exécute le moteur de paie réel
 * (CountryRulesInterface via PayrollCalculator) avec un barème fourni en
 * paramètre (`slabs_override`), ou le barème actuel s'il est absent.
 * La réponse détaille le calcul ligne par ligne (cotisations, assiette,
 * impôt par tranche, net, coût employeur).
 */
class PayrollSimulationController extends Controller
{
    public function __construct(private readonly PayrollCalculator $payrollCalculator) {}

    public function simulate(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user instanceof Employee) {
            if (! $user->isManager()) {
                abort(403);
            }
        } elseif (! $user instanceof SuperAdmin) {
            abort(401);
        }

        $validated = $request->validate([
            'gross_salary' => ['required', 'numeric', 'min:0'],
'country_code' => ['required', 'string', Rule::in(CountryRulesResolver::payrollCountryCodes())],
            'slabs_override' => ['sometimes', 'array', 'min:1'],
            'slabs_override.*.min' => ['required_with:slabs_override', 'numeric', 'min:0'],
            'slabs_override.*.max' => ['nullable', 'numeric', 'min:0'],
            'slabs_override.*.rate' => ['required_with:slabs_override', 'numeric', 'min:0', 'max:100'],
            'slabs_override.*.fixed_deduction' => ['sometimes', 'numeric', 'min:0'],
            'ignore_caps' => ['sometimes', 'boolean'],
        ]);

        /** @var array{gross_salary: float|string, country_code: string, slabs_override?: array<int, array{min: float|string, max?: float|string|null, rate: float|string, fixed_deduction?: float|string}>, ignore_caps?: bool} $validated */
        $gross = (float) $validated['gross_salary'];
        $countryCode = $validated['country_code'];

        $rules = $this->payrollCalculator->getRules($countryCode);

        // Override dry-run du barème (non persistant).
        if (isset($validated['slabs_override'])) {
            $slabs = array_map(static fn (array $slab): array => [
                'min' => (float) $slab['min'],
                'max' => ($slab['max'] ?? null) !== null ? (float) $slab['max'] : null,
                'rate' => (float) $slab['rate'],
                'fixed_deduction' => (float) ($slab['fixed_deduction'] ?? 0),
            ], $validated['slabs_override']);

            $rules->withTaxSlabs($slabs);
        }

        // Issue #1815 : comparaison « avec/sans plafond légal ». La méthode
        // vit sur AbstractCountryRules (pas sur le contrat) — garde instanceof.
        if ($rules instanceof AbstractCountryRules) {
            $rules->withCapsEnabled(! (bool) ($validated['ignore_caps'] ?? false));
        }

        /** @var array{employee: float, employer: float} $social */
        $social = $rules->calculateSocialCharges($gross);

        $taxBase = round($gross - $social['employee'], 2);

        // Impôt par tranche (même logique que calculateProgressiveTax).
        $bySlab = [];
        $tax = 0.0;
        foreach ($rules->taxSlabs() as $slab) {
            $lowerBound = (float) $slab['min'];
            if ($lowerBound > 0) {
                $lowerBound -= 1;
            }
            $upperBound = $slab['max'] === null ? PHP_FLOAT_MAX : (float) $slab['max'];
            $taxableInSlab = min($taxBase, $upperBound) - $lowerBound;
            if ($taxableInSlab <= 0) {
                continue;
            }
            $slabTax = round($taxableInSlab * ((float) $slab['rate'] / 100), 2);
            $tax += $slabTax;
            $bySlab[] = [
                'min' => (float) $slab['min'],
                'max' => $slab['max'],
                'rate' => (float) $slab['rate'],
                'taxable_amount' => round($taxableInSlab, 2),
                'tax' => $slabTax,
            ];
        }

        // L'impôt réel du pays (abattements DZ, etc.) prime sur la somme brute.
        $incomeTax = $rules->calculateIncomeTax($taxBase, 12, $gross);
        $netSalary = round($gross - $social['employee'] - $incomeTax, 2);
        $totalCost = round($gross + $social['employer'], 2);

        return response()->json([
            'data' => [
                'gross' => $gross,
                'country_code' => $countryCode,
                'social_employee' => $social['employee'],
                'social_employer' => $social['employer'],
                'tax_base' => $taxBase,
                'income_tax' => $incomeTax,
                'income_tax_by_slab' => $bySlab,
                'net' => $netSalary,
                'total_cost' => $totalCost,
            ],
        ]);
    }
}
