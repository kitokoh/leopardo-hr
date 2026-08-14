<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculationPresenter;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Rules\PayrollSupportedCountry;

/**
 * Simulation de cotisations sociales et d'impôt sur le revenu.
 *
 * Issue #1782 : ce contrôleur ne duplique PLUS aucune table de taux.
 * La source de vérité unique est le moteur de paie
 * (`PayrollCalculator::getRules()` → `CountryRulesInterface`), qui résout
 * DZ, MA, TN, FR, TR, SN, CEMAC×6, CEDEAO×6 et CA avec les mêmes règles que
 * les vrais bulletins — taux, caps, barèmes et abattements compris.
 *
 * Issue #1869 : la simulation et le bulletin passent par le MÊME noyau de
 * calcul (`PayrollCalculator::computeNetBreakdown()`), ce qui garantit des
 * résultats identiques pour un même brut et un même contexte de règles.
 * La réponse expose :
 *   - au niveau racine, les champs historiques (rétro-compatibles) ;
 *   - sous `contract`, le contrat complet et explicable (pays, devise,
 *     identifiant/version des règles, période, politique d'arrondi,
 *     bracket_tax, retenues totales…) — docs/payroll/CALCULATION_CONTRACT.md.
 */
class CotisationSimulationController extends Controller
{
    public function __construct(
        private readonly PayrollCalculator $payrollCalculator,
        private readonly PayrollCalculationPresenter $presenter,
    ) {}

    public function simulate(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validate([
            'gross_salary' => 'required|numeric|min:0',
            'country_code' => ['required', 'string', new PayrollSupportedCountry],
        ]);

        /** @var array{gross_salary: float|string, country_code: string} $validated */
        $gross = (float) $validated['gross_salary'];
        $countryCode = $validated['country_code'];

        // Pays inconnu → 422 explicite (UnsupportedCountryRulesException,
        // rendue par le handler d'exceptions avec un message métier clair).
        $rules = $this->payrollCalculator->getRules($countryCode);

        // Issue #1869 — mêmes appels métier que PayrollCalculator::calculateSlip().
        $breakdown = $this->payrollCalculator->computeNetBreakdown($gross, $rules);
        $social = $breakdown['social'];

        $employeeContributions = [];
        $employerContributions = [];
        foreach ($rules->socialContributions() as $contribution) {
            // Base plafonnée quand la règle déclare un cap (sinon brut entier).
            $base = $contribution['cap'] === null
                ? $gross
                : min($gross, (float) $contribution['cap']);

            $item = [
                'name' => $contribution['name'],
                'code' => $contribution['code'],
                'rate' => $contribution['rate'],
                'cap' => $contribution['cap'],
                'amount' => round($base * (float) $contribution['rate'] / 100, 2),
            ];

            if ($contribution['type'] === 'employee') {
                $employeeContributions[] = $item;
            } else {
                $employerContributions[] = $item;
            }
        }

        return response()->json([
            'data' => [
                // ── Champs historiques (rétro-compatibles) ───────────────────
                'country_code' => $countryCode,
                'gross_salary' => $gross,
                'employee_contributions' => $employeeContributions,
                'employer_contributions' => $employerContributions,
                'total_employee_deduction' => $social['employee'],
                'total_employer_cost' => $social['employer'],
                'taxable_gross' => round($breakdown['taxable_gross'], 2),
                'income_tax' => $breakdown['income_tax'],
                'bracket_tax' => $breakdown['bracket_tax'],
                'total_deductions' => round($breakdown['base_deductions'], 2),
                // Rétro-compatible : brut − cotisations salariales (sans impôt).
                'net_before_tax' => round($gross - $social['employee'], 2),
                // Net réel = brut − retenues totales (issue #1782 + #1869).
                'net_salary' => $breakdown['net_salary'],
                'total_cost_employer' => $breakdown['total_cost'],
                // ── Contrat complet et explicable (issue #1869) ──────────────
                'contract' => $this->presenter->present($countryCode, $gross),
            ],
        ]);
    }
}
