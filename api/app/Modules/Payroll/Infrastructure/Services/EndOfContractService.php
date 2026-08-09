<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Planning\Domain\Models\Absence;
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
 * Régime DZ (documenté, à valider comptable) :
 *  - préavis : 0 par défaut (à renseigner selon le contrat / l'exécution) ;
 *  - indemnité d'ancienneté : 1 mois de salaire par année (Loi 90-11, plafond
 *    légal non appliqué ici — à paramétrer par entreprise).
 */
class EndOfContractService
{
    public function __construct(private readonly PayrollCalculator $calculator = new PayrollCalculator()) {}

    /**
     * Solde de tout compte à une date donnée (défaut : aujourd'hui).
     *
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
    public function settlement(Employee $employee, ?Carbon $endDate = null): array
    {
        $endDate = $endDate ?? Carbon::today();
        $monthlyBase = $this->monthlyBase($employee);
        $workingDays = PayrollCalculator::STANDARD_WORKING_DAYS;

        $yearsOfService = $this->yearsOfService($employee, $endDate);
        $proratedDays = $this->proratedDays($employee, $endDate);
        $unpaidLeaveDays = $this->unpaidLeaveDays($employee, $endDate);
        $referenceGross = $this->referenceGross12Months($employee, $endDate);

        $breakdown = $this->calculator->computeFinalSettlement(
            monthlyBase: $monthlyBase,
            yearsOfService: $yearsOfService,
            proratedDays: $proratedDays,
            workingDays: $workingDays,
            unpaidLeaveDays: $unpaidLeaveDays,
            referenceGross12Months: $referenceGross,
            severanceMonthsPerYear: 1.0,
            noticeDays: 0.0,
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

    /** @return array{employee: Employee, company: ?\App\Core\Tenant\Domain\Models\Company, months_of_service: int, settlement: array<string, mixed>} */
    public function certificateData(Employee $employee, ?Carbon $endDate = null): array
    {
        $endDate = $endDate ?? Carbon::today();

        return [
            'employee' => $employee,
            'company' => $employee->company,
            'months_of_service' => $this->monthsOfService($employee, $endDate),
            'settlement' => $this->settlement($employee, $endDate),
        ];
    }

    private function monthlyBase(Employee $employee): float
    {
        if ($employee->salary_base !== null && (float) $employee->salary_base > 0.0) {
            return (float) $employee->salary_base;
        }

        $structure = \App\Modules\Payroll\Domain\Models\SalaryStructure::query()
            ->where('company_id', $employee->company_id)
            ->where('active', true)
            ->where('country_code', $employee->company?->country ?? 'DZ')
            ->first();

        return $structure?->base_salary ?? 0.0;
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

        return max(0, $employee->contract_start->diffInMonths($endDate));
    }

    private function proratedDays(Employee $employee, Carbon $endDate): float
    {
        $monthStart = $endDate->copy()->startOfMonth();
        $start = $employee->contract_start !== null && $employee->contract_start->gt($monthStart)
            ? $employee->contract_start
            : $monthStart;

        return max(0.0, (float) $start->diffInDays($endDate) + 1);
    }

    private function unpaidLeaveDays(Employee $employee, Carbon $endDate): float
    {
        $monthStart = $endDate->copy()->startOfMonth();

        return (float) Absence::query()
            ->where('company_id', $employee->company_id)
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereBetween('start_date', [$monthStart, $endDate])
            ->whereHas('absenceType', fn ($q) => $q->where('is_paid', false))
            ->sum('days_count');
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
