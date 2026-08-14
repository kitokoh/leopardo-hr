<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Simulation de cotisations sociales et d'impôt sur le revenu.
 *
 * Issue #1782 : ce contrôleur ne duplique PLUS aucune table de taux.
 * La source de vérité unique est le moteur de paie
 * (`PayrollCalculator::getRules()` → `CountryRulesInterface`), qui résout
 * DZ, MA, TN, FR, TR, SN, CEMAC×6, CEDEAO×6 et CA avec les mêmes règles que
 * les vrais bulletins — taux, caps, barèmes et abattements compris.
 *
 * La logique reproduit fidèlement `PayrollCalculator::calculateSlip()` :
 *   1. cotisations sociales (`calculateSocialCharges`, valeurs définitives) ;
 *   2. assiette fiscale = brut − cotisations salariales ;
 *   3. impôt progressif (`calculateIncomeTax`, mêmes barèmes que le bulletin) ;
 *   4. net réel = brut − cotisations salariales − impôt.
 */
class CotisationSimulationController extends Controller
{
    public function __construct(private readonly PayrollCalculator $payrollCalculator) {}

    public function simulate(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validate([
            'gross_salary' => 'required|numeric|min:0',
            'country_code' => 'required|string|in:DZ,MA,FR,TN,TR,SN,CM,CF,TD,CG,GA,GQ,CI,ML,BF,BJ,TG,NE,CA',
        ]);

        /** @var array{gross_salary: float|string, country_code: string} $validated */
        $gross = (float) $validated['gross_salary'];
        $countryCode = $validated['country_code'];

        $rules = $this->payrollCalculator->getRules($countryCode);

        /** @var array{employee: float, employer: float} $social */
        $social = $rules->calculateSocialCharges($gross);

        $taxableGross = round($gross - $social['employee'], 2);
        // Même appel que PayrollCalculator::calculateSlip() : le 2e paramètre
        // (annualBasis) a un défaut de 12 dans le contrat et toutes les règles.
        $incomeTax = $rules->calculateIncomeTax($taxableGross);
        $netSalary = round($gross - $social['employee'] - $incomeTax, 2);

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
                'country_code' => $countryCode,
                'gross_salary' => $gross,
                'employee_contributions' => $employeeContributions,
                'employer_contributions' => $employerContributions,
                'total_employee_deduction' => $social['employee'],
                'total_employer_cost' => $social['employer'],
                'taxable_gross' => $taxableGross,
                'income_tax' => $incomeTax,
                // Rétro-compatible : brut − cotisations salariales (sans impôt).
                'net_before_tax' => round($gross - $social['employee'], 2),
                // Net réel = brut − cotisations salariales − impôt (issue #1782).
                'net_salary' => $netSalary,
                'total_cost_employer' => round($gross + $social['employer'], 2),
            ],
        ]);
    }
}
