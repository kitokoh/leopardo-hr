<?php

declare(strict_types=1);

namespace App\AI\Planning;

use App\Models\Absence;
use App\Models\Contract;
use App\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PlanningOptimizer
{
    public function optimizeWeeklyPlanning(int $companyId, string $weekStart): array
    {
        $start = Carbon::parse($weekStart)->startOfWeek();
        $end = $start->copy()->endOfWeek();

        $employees = Employee::where('company_id', $companyId)
            ->where('status', 'active')
            ->with(['department', 'position'])
            ->get();

        $absences = Absence::where('company_id', $companyId)
            ->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start)
            ->whereIn('status', ['approved', 'pending'])
            ->get();

        $expiringContracts = Contract::where('company_id', $companyId)
            ->where('status', 'active')
            ->where('end_date', '<=', $end->copy()->addDays(30))
            ->where('end_date', '>=', $start)
            ->get();

        $departmentCoverage = $this->analyzeDepartmentCoverage($employees, $absences, $start, $end);
        $conflicts = $this->detectSchedulingConflicts($absences, $departmentCoverage);
        $recommendations = $this->generateRecommendations($departmentCoverage, $conflicts, $expiringContracts);

        return [
            'week' => $start->toDateString().' - '.$end->toDateString(),
            'total_employees' => $employees->count(),
            'total_absences' => $absences->count(),
            'department_coverage' => $departmentCoverage,
            'conflicts' => $conflicts,
            'expiring_contracts' => $expiringContracts->count(),
            'recommendations' => $recommendations,
            'optimization_score' => $this->calculateScore($departmentCoverage, $conflicts),
        ];
    }

    public function suggestShiftRebalancing(int $companyId): array
    {
        $employees = Employee::where('company_id', $companyId)
            ->where('status', 'active')
            ->with('department')
            ->get();

        $departmentSizes = $employees->groupBy(fn ($e) => $e->department?->name ?? 'Non affecte')
            ->map(fn (Collection $group) => $group->count())
            ->toArray();

        $avgSize = count($departmentSizes) > 0
            ? array_sum($departmentSizes) / count($departmentSizes)
            : 0;

        $suggestions = [];
        foreach ($departmentSizes as $dept => $size) {
            if ($size > $avgSize * 1.5) {
                $suggestions[] = [
                    'department' => $dept,
                    'current_size' => $size,
                    'suggestion' => 'sureffectif',
                    'detail' => 'Le departement '.((string) $dept).' a '.((string) $size).' employes, superieur a 1.5x la moyenne ('.round($avgSize).').',
                ];
            } elseif ($size < $avgSize * 0.5 && $size > 0) {
                $suggestions[] = [
                    'department' => $dept,
                    'current_size' => $size,
                    'suggestion' => 'sous-effectif',
                    'detail' => 'Le departement '.((string) $dept).' a seulement '.((string) $size).' employes, inferieur a 0.5x la moyenne ('.round($avgSize).').',
                ];
            }
        }

        return [
            'departments' => $departmentSizes,
            'average_size' => round($avgSize, 1),
            'suggestions' => $suggestions,
        ];
    }

    private function analyzeDepartmentCoverage(Collection $employees, Collection $absences, Carbon $start, Carbon $end): array
    {
        $coverage = [];
        $grouped = $employees->groupBy(fn ($e) => $e->department?->name ?? 'Non affecte');

        foreach ($grouped as $dept => $deptEmployees) {
            $total = $deptEmployees->count();
            $absentIds = $absences->whereIn('employee_id', $deptEmployees->pluck('id'))
                ->pluck('employee_id')
                ->unique()
                ->count();

            $available = $total - $absentIds;
            $rate = $total > 0 ? round(($available / $total) * 100, 1) : 0;

            $coverage[$dept] = [
                'total' => $total,
                'absent' => $absentIds,
                'available' => $available,
                'coverage_rate' => $rate,
                'status' => $rate >= 80 ? 'ok' : ($rate >= 50 ? 'warning' : 'critical'),
            ];
        }

        return $coverage;
    }

    private function detectSchedulingConflicts(Collection $absences, array $departmentCoverage): array
    {
        $conflicts = [];

        foreach ($departmentCoverage as $dept => $info) {
            if ($info['status'] === 'critical') {
                $conflicts[] = [
                    'type' => 'low_coverage',
                    'department' => $dept,
                    'coverage_rate' => $info['coverage_rate'],
                    'severity' => 'high',
                    'message' => "Couverture critique ($info[coverage_rate]%) pour $dept.",
                ];
            }
        }

        $overlapping = $absences->groupBy('employee_id')
            ->filter(fn (Collection $group) => $group->count() > 1);

        foreach ($overlapping as $employeeId => $employeeAbsences) {
            $conflicts[] = [
                'type' => 'overlapping_absences',
                'employee_id' => $employeeId,
                'count' => $employeeAbsences->count(),
                'severity' => 'medium',
                'message' => "Employe #$employeeId a {$employeeAbsences->count()} absences sur la meme periode.",
            ];
        }

        return $conflicts;
    }

    private function generateRecommendations(array $departmentCoverage, array $conflicts, Collection $expiringContracts): array
    {
        $recommendations = [];

        foreach ($departmentCoverage as $dept => $info) {
            if ($info['status'] === 'critical') {
                $recommendations[] = [
                    'priority' => 'high',
                    'action' => "Renforcer l'equipe $dept cette semaine (couverture {$info['coverage_rate']}%).",
                ];
            } elseif ($info['status'] === 'warning') {
                $recommendations[] = [
                    'priority' => 'medium',
                    'action' => "Surveiller la couverture de $dept ({$info['coverage_rate']}%).",
                ];
            }
        }

        if ($expiringContracts->count() > 0) {
            $recommendations[] = [
                'priority' => 'high',
                'action' => "{$expiringContracts->count()} contrat(s) expirent dans les 30 jours. Planifier les renouvellements.",
            ];
        }

        return $recommendations;
    }

    private function calculateScore(array $departmentCoverage, array $conflicts): int
    {
        $score = 100;

        foreach ($departmentCoverage as $info) {
            if ($info['status'] === 'critical') {
                $score -= 20;
            } elseif ($info['status'] === 'warning') {
                $score -= 10;
            }
        }

        $score -= count($conflicts) * 5;

        return max(0, min(100, $score));
    }
}
