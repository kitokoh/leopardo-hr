<?php

declare(strict_types=1);

namespace App\AI\Predictions;

use Illuminate\Support\Facades\DB;

class TurnoverPredictor
{
    /**
     * @return array{risk_score: float, high_risk_employees: list<array{employee_id: int, name: string, risk: float, factors: list<string>}>, department_risks: list<array{department: string, risk: float, headcount: int}>, overall_turnover_rate: float}
     */
    public function predict(int $companyId, int $months = 6): array
    {
        $employees = DB::table('employees')
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->select([
                'id',
                'first_name',
                'last_name',
                'department_id',
                'position_id',
                'hire_date',
                'salary_base',
            ])
            ->get();

        $recentDepartures = DB::table('employees')
            ->where('company_id', $companyId)
            ->where('status', 'terminated')
            ->where('updated_at', '>=', now()->subMonths(12))
            ->count();

        $totalActive = $employees->count();
        $overallRate = $totalActive > 0 ? round(($recentDepartures / $totalActive) * 100, 2) : 0;

        $highRisk = [];
        $departmentRisks = [];

        foreach ($employees->groupBy('department_id') as $deptId => $deptEmployees) {
            $deptTerminated = DB::table('employees')
                ->where('company_id', $companyId)
                ->where('department_id', $deptId)
                ->where('status', 'terminated')
                ->where('updated_at', '>=', now()->subMonths(12))
                ->count();

            $deptName = DB::table('departments')
                ->where('id', $deptId)
                ->value('name') ?? 'Non assigne';

            $deptRisk = $deptEmployees->count() > 0
                ? round(($deptTerminated / $deptEmployees->count()) * 100, 2)
                : 0;

            $departmentRisks[] = [
                'department' => $deptName,
                'risk' => $deptRisk,
                'headcount' => $deptEmployees->count(),
            ];

            foreach ($deptEmployees as $emp) {
                $factors = [];
                $risk = 0.0;

                /** @var string|null $hireDate */
                $hireDate = $emp->hire_date;
                if ($hireDate) {
                    $tenure = now()->diffInMonths($hireDate);
                    if ($tenure < 12) {
                        $risk += 15;
                        $factors[] = 'Anciennete < 1 an';
                    }
                    if ($tenure > 60) {
                        $risk += 5;
                        $factors[] = 'Anciennete > 5 ans (plateau)';
                    }
                }

                if ($deptRisk > 20) {
                    $risk += 10;
                    $factors[] = 'Departement a fort turnover';
                }

                $absenceCount = DB::table('absences')
                    ->where('employee_id', $emp->id)
                    ->where('start_date', '>=', now()->subMonths(6))
                    ->count();

                if ($absenceCount > 5) {
                    $risk += 10;
                    $factors[] = 'Absences frequentes';
                }

                if ($risk >= 20) {
                    $highRisk[] = [
                        'employee_id' => $emp->id,
                        'name' => $emp->first_name . ' ' . $emp->last_name,
                        'risk' => min($risk, 100),
                        'factors' => $factors,
                    ];
                }
            }
        }

        usort($highRisk, fn (array $a, array $b) => $b['risk'] <=> $a['risk']);
        usort($departmentRisks, fn (array $a, array $b) => $b['risk'] <=> $a['risk']);

        $avgRisk = count($highRisk) > 0
            ? round(array_sum(array_column($highRisk, 'risk')) / count($highRisk), 2)
            : 0;

        return [
            'risk_score' => $avgRisk,
            'high_risk_employees' => array_slice($highRisk, 0, 20),
            'department_risks' => $departmentRisks,
            'overall_turnover_rate' => $overallRate,
        ];
    }
}
