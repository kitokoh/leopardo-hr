<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Contracts\CountryRulesInterface;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use App\Modules\Planning\Domain\Models\LeaveBalance;
use Carbon\Carbon;

/**
 * Programme FOCUS — F-08 (#1538) : solde de tout compte et certificat de
 * travail en fin de contrat.
 *
 * Le calcul de détail est délégué à `PayrollCalculator::computeFinalSettlement`
 * (golden tests F-08) ; ce service alimente les données réelles de l'employé :
 * salaire de base, ancienneté (contract_start), jours de congés non pris,
 * référence 12 mois, préavis.
 *
 * Régime DZ (documenté, à valider comptable — FOCUS 2 F-31, règles pays) :
 *  - préavis : durée légale résolue via `CountryRulesInterface::noticePeriodDays`
 *    (valeur pilote DZ : 0 — à renseigner selon le contrat / l'exécution) ;
 *  - indemnité d'ancienneté : 1 mois de salaire par année via
 *    `CountryRulesInterface::severanceMonthsPerYear` (Loi 90-11, plafond
 *    légal non appliqué ici — à paramétrer par entreprise).
 */
class EndOfContractService
{
    public function __construct(private readonly PayrollCalculator $calculator = new PayrollCalculator) {}

    /**
     * Solde de tout compte à une date donnée (défaut : aujourd'hui).
     *
     * @param  array{departure_reason?: string|null, notice_served?: bool}  $context
     *                                                                                Issue #1943 — conditionnement du préavis : l'indemnité
     *                                                                                compensatrice n'est due que si l'employeur licencie un CDI
     *                                                                                (hors faute lourde) et dispense du préavis. Sans contexte
     *                                                                                explicite, le défaut est PRUDENT : pas de préavis calculé
     *                                                                                (un CDD à terme naturel, une démission ou une faute lourde ne
     *                                                                                doivent jamais payer de préavis — surpaie silencieuse sinon).
     * @return array{
     *   employee_id: int,
     *   end_date: string,
     *   years_of_service: float,
     *   monthly_base: float,
     *   working_days: float,
     *   prorated_days: float,
     *   unpaid_leave_days: float,
     *   reference_gross_12_months: float,
     *   breakdown: array{prorated_pay: float, leave_indemnity: float, notice_pay: float, severance: float, total: float}
     * }
     */
    public function settlement(Employee $employee, ?Carbon $endDate = null, array $context = []): array
    {
        $endDate = $endDate ?? $this->resolveEndDate($employee);
        $monthlyBase = $this->monthlyBase($employee);
        $workingDays = PayrollCalculator::STANDARD_WORKING_DAYS;

        $yearsOfService = $this->yearsOfService($employee, $endDate);
        $proratedDays = $this->proratedDays($employee, $endDate);
        $unpaidLeaveDays = $this->unpaidLeaveDays($employee, $endDate);
        $referenceGross = $this->referenceGross12Months($employee, $endDate);

        // FOCUS 2 (F-31) : préavis et indemnité de licenciement sont portés
        // par les règles pays (CountryRulesInterface) au lieu de valeurs codées
        // en dur — la valeur applicable est résolue selon le pays de la société
        // (défaut DZ) et l'ancienneté de l'employé.
        // #1868 : PLUS AUCUN repli silencieux vers DZ — un pays non enregistré
        // propage UnsupportedCountryRulesException (422 explicite), comme le
        // vérifie GoldenDzEndOfContractRulesTest.
        $countryCode = $employee->company->country ?? 'DZ';

        $rules = $this->calculator->getRules($countryCode);

        // Issue #1943 — le préavis est conditionné au contexte de départ
        // (CDI + licenciement hors faute lourde + préavis non effectué).
        $noticeDays = $this->resolveNoticeDays($rules, $employee, $context, $yearsOfService);

        $breakdown = $this->calculator->computeFinalSettlement(
            monthlyBase: $monthlyBase,
            yearsOfService: $yearsOfService,
            proratedDays: $proratedDays,
            workingDays: $workingDays,
            unpaidLeaveDays: $unpaidLeaveDays,
            referenceGross12Months: $referenceGross,
            severanceMonthsPerYear: $rules->severanceMonthsPerYear($yearsOfService),
            noticeDays: $noticeDays,
        );

        return [
            'employee_id' => $employee->id,
            'end_date' => $endDate->toDateString(),
            'years_of_service' => round($yearsOfService, 2),
            'monthly_base' => $monthlyBase,
            'working_days' => $workingDays,
            'prorated_days' => $proratedDays,
            'unpaid_leave_days' => $unpaidLeaveDays,
            'reference_gross_12_months' => $referenceGross,
            'breakdown' => $breakdown,
        ];
    }

    /** @return array{employee: Employee, company: ?Company, months_of_service: int, settlement: array<string, mixed>} */
    public function certificateData(Employee $employee, ?Carbon $endDate = null): array
    {
        $endDate = $endDate ?? $this->resolveEndDate($employee);

        return [
            'employee' => $employee,
            'company' => $employee->company,
            'months_of_service' => $this->monthsOfService($employee, $endDate),
            'settlement' => $this->settlement($employee, $endDate),
        ];
    }

    /**
     * Issue #1943 — résout les jours de préavis APPLICABLES au départ.
     *
     * L'indemnité compensatrice de préavis n'est due que si l'employeur
     * licencie un CDI (hors faute lourde) et dispense du préavis (Loi 90-11
     * art. 98). Elle n'est PAS due pour :
     *  - un CDD à terme naturel (le contrat s'achève, aucun préavis) ;
     *  - une démission (le préavis est à la charge du salarié, pas payé) ;
     *  - une faute lourde (aucun préavis, art. 83/88) ;
     *  - un préavis réellement effectué (rien à compenser).
     *
     * Défaut sans contexte : 0 (prudent — on ne paye pas un préavis dont on
     * ne connaît pas le cadre). Les appelants qui connaissent le contexte
     * passent `departure_reason` (dismissal/redundancy/economic → préavis dû)
     * et `notice_served=false`.
     *
     * @param  array{departure_reason?: string|null, notice_served?: bool}  $context
     */
    private function resolveNoticeDays(
        CountryRulesInterface $rules,
        Employee $employee,
        array $context,
        float $yearsOfService
    ): float {
        // Préavis effectué → rien à compenser.
        if (($context['notice_served'] ?? false) === true) {
            return 0.0;
        }

        // Contrat : seul un CDI (licenciement) peut générer un préavis dû par
        // l'employeur. CDD/Stage/Consultant → 0.
        $contractType = strtoupper((string) ($employee->contract_type ?? ''));
        if ($contractType !== 'CDI') {
            return 0.0;
        }

        // Motif : licenciement hors faute lourde → préavis ; démission, fin de
        // CDD, faute lourde, accord mutuel → 0.
        $reason = strtolower((string) ($context['departure_reason'] ?? ''));
        if ($reason === '') {
            // Contexte absent : prudent → pas de préavis (l'appelant doit
            // préciser le motif pour activer l'indemnité).
            return 0.0;
        }

        if (in_array($reason, ['dismissal', 'redundancy', 'economic', 'layoff', 'licenciement', 'licenciement-economique'], true)) {
            // Catégorie professionnelle (SN #2123 : cadre/general/ouvrier via
            // employees.ipres_category) — les règles pays décident de la durée.
            return $rules->noticePeriodDays($yearsOfService, $employee->ipres_category);
        }

        return 0.0;
    }

    private function monthlyBase(Employee $employee): float
    {
        if ($employee->salary_base !== null && (float) $employee->salary_base > 0.0) {
            return (float) $employee->salary_base;
        }

        $structure = SalaryStructure::query()
            ->where('company_id', $employee->company_id)
            ->where('active', true)
            ->where('country_code', $employee->company->country ?? 'DZ')
            ->first();

        return $structure->base_salary ?? 0.0;
    }

    private function resolveEndDate(Employee $employee): Carbon
    {
        return $employee->contract_end !== null
            ? Carbon::parse($employee->contract_end)
            : Carbon::today();
    }

    private function yearsOfService(Employee $employee, Carbon $endDate): float
    {
        return $this->monthsOfService($employee, $endDate) / 12.0;
    }

    private function monthsOfService(Employee $employee, Carbon $endDate): int
    {
        if ($employee->contract_start === null) {
            return 0;
        }

        return (int) max(0, $employee->contract_start->diffInMonths($endDate));
    }

    private function proratedDays(Employee $employee, Carbon $endDate): float
    {
        $monthStart = $endDate->copy()->startOfMonth();
        $start = $employee->contract_start !== null && $employee->contract_start->gt($monthStart)
            ? $employee->contract_start
            : $monthStart;

        // Jours OUVRÉS (lun-ven) effectués dans le mois de départ : le prorata
        // est ensuite divisé par STANDARD_WORKING_DAYS (22) côté
        // computeFinalSettlement — mélanger jours calendaires et jours ouvrés
        // fausserait le calcul (départ au 15 → 15/22 au lieu de ~11/22).
        $businessDays = 0;
        $cursor = $start->copy();
        while ($cursor->lte($endDate)) {
            if ($cursor->isWeekday()) {
                $businessDays++;
            }
            $cursor->addDay();
        }

        return max(0.0, (float) $businessDays);
    }

    private function unpaidLeaveDays(Employee $employee, Carbon $endDate): float
    {
        // Congés payés ACQUIS et non pris au départ (indemnité compensatrice) :
        // LeaveBalance.balance = solde restant pour l'année (acquis − pris).
        // Les absences sans solde sont déjà déduites du bulletin du mois et ne
        // doivent pas être re-payées ici (F-08, doc solde de tout compte DZ).
        $year = (int) $endDate->format('Y');

        return (float) LeaveBalance::query()
            ->where('company_id', $employee->company_id)
            ->where('employee_id', $employee->id)
            ->where('year', $year)
            ->whereHas('absenceType', fn ($q) => $q->where('is_paid', true))
            ->sum('balance');
    }

    private function referenceGross12Months(Employee $employee, Carbon $endDate): float
    {
        $twelveMonthsAgo = $endDate->copy()->subMonths(12);

        $gross = PaySlip::query()
            ->where('company_id', $employee->company_id)
            ->where('employee_id', $employee->id)
            ->where('status', 'validated')
            ->where('period_start', '>=', $twelveMonthsAgo)
            ->where('period_end', '<=', $endDate)
            ->sum('gross_salary');

        return $gross > 0.0 ? (float) $gross : $this->monthlyBase($employee) * 12.0;
    }
}
