<?php

declare(strict_types=1);

namespace App\AI\Predictions;

use Illuminate\Support\Facades\DB;

class AbsenteeismPredictor
{
    /**
     * @return array{predicted_days_next_month: float, high_risk_periods: list<array{month: string, predicted_rate: float}>, department_predictions: list<array{department: string, predicted_days: float, historical_avg: float}>, recommendations: list<string>}
     */
    public function predict(string $companyId, int $horizonMonths = 3): array
    {
        $historicalData = [];

        for ($i = 12; $i >= 1; $i--) {
            $start = now()->subMonths($i)->startOfMonth();
            $end = now()->subMonths($i)->endOfMonth();

            $totalDays = DB::table('absences')
                ->join('employees', 'absences.employee_id', '=', 'employees.id')
                ->where('employees.company_id', $companyId)
                ->where('absences.start_date', '>=', $start)
                ->where('absences.start_date', '<=', $end)
                ->where('absences.status', 'approved')
                ->sum(DB::raw('EXTRACT(DAY FROM absences.end_date - absences.start_date) + 1'));

            /** @var numeric $totalDays */
            $historicalData[] = [
                'month' => $start->format('Y-m'),
                'days' => (float) $totalDays,
            ];
        }

        $avgDays = count($historicalData) > 0
            ? array_sum(array_column($historicalData, 'days')) / count($historicalData)
            : 0;

        $recentAvg = count($historicalData) >= 3
            ? array_sum(array_map(fn (array $d) => $d['days'], array_slice($historicalData, -3))) / 3
            : $avgDays;

        $trend = $recentAvg > $avgDays ? 1.05 : 0.98;

        $highRiskPeriods = [];
        for ($i = 1; $i <= $horizonMonths; $i++) {
            $futureMonth = now()->addMonths($i);
            $seasonFactor = in_array($futureMonth->month, [7, 8, 12]) ? 1.3 : 1.0;

            $highRiskPeriods[] = [
                'month' => $futureMonth->format('Y-m'),
                'predicted_rate' => round($recentAvg * $trend * $seasonFactor, 1),
            ];
        }

        $departments = DB::table('departments')
            ->where('company_id', $companyId)
            ->select('id', 'name')
            ->get();

        $deptPredictions = [];
        foreach ($departments as $dept) {
            /** @var string $deptName */
            $deptName = $dept->name;
            $deptHistorical = DB::table('absences')
                ->join('employees', 'absences.employee_id', '=', 'employees.id')
                ->where('employees.company_id', $companyId)
                ->where('employees.department_id', $dept->id)
                ->where('absences.start_date', '>=', now()->subMonths(6))
                ->where('absences.status', 'approved')
                ->sum(DB::raw('EXTRACT(DAY FROM absences.end_date - absences.start_date) + 1'));

            /** @var numeric $deptHistorical */
            $deptAvg = (float) $deptHistorical / 6;

            $deptPredictions[] = [
                'department' => $deptName,
                'predicted_days' => round($deptAvg * $trend, 1),
                'historical_avg' => round($deptAvg, 1),
            ];
        }

        usort($deptPredictions, fn (array $a, array $b) => $b['predicted_days'] <=> $a['predicted_days']);

        $recommendations = [];
        if ($recentAvg > $avgDays * 1.2) {
            $recommendations[] = 'Taux d\'absenteisme en hausse significative — analyser les causes par departement';
        }
        if (count(array_filter($deptPredictions, fn (array $d) => $d['predicted_days'] > $avgDays * 1.5)) > 0) {
            $recommendations[] = 'Certains departements depassent significativement la moyenne — entretiens RH recommandes';
        }
        if (in_array(now()->addMonth()->month, [7, 8, 12])) {
            $recommendations[] = 'Periode de conges approche — verifier les soldes et planifier les remplacements';
        }
        if (empty($recommendations)) {
            $recommendations[] = 'Situation stable — maintenir la surveillance reguliere';
        }

        return [
            'predicted_days_next_month' => round($recentAvg * $trend, 1),
            'high_risk_periods' => $highRiskPeriods,
            'department_predictions' => $deptPredictions,
            'recommendations' => $recommendations,
        ];
    }
}
