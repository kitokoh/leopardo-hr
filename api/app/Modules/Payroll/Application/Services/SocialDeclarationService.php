<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Services;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Extraction partagée des données employés/bulletins pour les déclarations
 * sociales (issue #3149). Avant ce service, SocialDeclarationController
 * réimplémentait la même agrégation pay_slips/payroll_runs inline dans
 * chaque méthode generate* — risque de divergence entre pays.
 *
 * Toutes les requêtes sont scopées par company_id (isolation tenant) et ne
 * lisent que des bulletins `validated`.
 */
class SocialDeclarationService
{
    /**
     * Employés actifs d'une entreprise (tri stable par id).
     *
     * @return Collection<int, Employee>
     */
    public function activeEmployees(string $companyId): Collection
    {
        return Employee::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->orderBy('id')
            ->get();
    }

    /**
     * Mois couverts par un trimestre civil.
     *
     * @return list<int>
     */
    public function quarterMonths(string $quarter): array
    {
        return match ($quarter) {
            'Q1' => [1, 2, 3],
            'Q2' => [4, 5, 6],
            'Q3' => [7, 8, 9],
            'Q4' => [10, 11, 12],
            default => throw new \InvalidArgumentException("Trimestre invalide : {$quarter}"),
        };
    }

    /**
     * Agrégation trimestrielle des bulletins validés par employé :
     * `total_gross` (somme des salaires bruts) et, si demandé,
     * `months_worked` (nombre de mois distincts avec bulletin).
     *
     * @param  list<int>  $months
     * @return \Illuminate\Support\Collection<int|string, \stdClass> keyBy employee_id
     */
    public function quarterPayrollData(string $companyId, int $year, array $months, bool $withMonthsCount = false)
    {
        $query = DB::table('pay_slips')
            ->join('payroll_runs', 'pay_slips.payroll_run_id', '=', 'payroll_runs.id')
            ->where('payroll_runs.company_id', $companyId)
            ->where('pay_slips.status', 'validated')
            ->whereYear('payroll_runs.period_start', $year)
            ->whereIn(DB::raw('EXTRACT(MONTH FROM payroll_runs.period_start)'), $months)
            ->select([
                'pay_slips.employee_id',
                DB::raw('SUM(pay_slips.gross_salary) as total_gross'),
            ]);

        if ($withMonthsCount) {
            $query->addSelect(DB::raw('COUNT(DISTINCT EXTRACT(MONTH FROM payroll_runs.period_start)) as months_worked'));
        }

        return $query
            ->groupBy('pay_slips.employee_id')
            ->get()
            ->keyBy('employee_id');
    }

    /**
     * Agrégation mensuelle des bulletins validés par employé :
     * `total_gross` + `total_net` (DSN France).
     *
     * @return \Illuminate\Support\Collection<int|string, \stdClass> keyBy employee_id
     */
    public function monthPayrollData(string $companyId, int $year, int $month)
    {
        return DB::table('pay_slips')
            ->join('payroll_runs', 'pay_slips.payroll_run_id', '=', 'payroll_runs.id')
            ->where('payroll_runs.company_id', $companyId)
            ->where('pay_slips.status', 'validated')
            ->whereYear('payroll_runs.period_start', $year)
            ->whereMonth('payroll_runs.period_start', $month)
            ->select([
                'pay_slips.employee_id',
                DB::raw('SUM(pay_slips.gross_salary) as total_gross'),
                DB::raw('SUM(pay_slips.net_salary) as total_net'),
            ])
            ->groupBy('pay_slips.employee_id')
            ->get()
            ->keyBy('employee_id');
    }
}
