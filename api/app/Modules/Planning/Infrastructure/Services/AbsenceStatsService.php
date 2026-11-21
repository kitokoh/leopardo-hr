<?php

declare(strict_types=1);

namespace App\Modules\Planning\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Planning\Domain\Models\Absence;
use Illuminate\Support\Carbon;

/**
 * Agrégations des absences (module `Planning`, propriétaire canonique des
 * modèles d'absence — cf. dérogation PA2-ARCH-002 dans ARCHITECTURE.md).
 *
 * Extrait de `Modules/Absence/Interfaces/.../AbsenceController::stats()`, qui
 * portait ~110 lignes de calcul dans la couche HTTP. `Modules/Absence` reste
 * une façade pure : il ne peut pas héberger de couche Infrastructure, donc le
 * service vit ici, à côté du modèle qu'il agrège.
 *
 * Le périmètre est TOUJOURS borné par `company_id` fourni par l'appelant.
 */
class AbsenceStatsService
{
    /**
     * Statistiques d'absences sur une période.
     *
     * @return array{
     *     total_absences: int,
     *     total_days: float,
     *     by_status: array{pending: int, approved: int, rejected: int},
     *     by_type: array<int, array{type: string, code: string, count: int, days: float}>,
     *     top_employees: array<int, array{employee_id: int|null, name: string, count: int, days: float}>,
     *     absenteeism_rate: float
     * }
     */
    public function forPeriod(
        string $companyId,
        string $from,
        string $to,
        ?int $employeeId = null,
    ): array {
        $query = Absence::query()
            ->where('company_id', $companyId)
            ->whereBetween('start_date', [$from, $to]);

        if ($employeeId !== null) {
            $query->where('employee_id', $employeeId);
        }

        $absences = $query
            ->with(['absenceType:id,name,code,deducts_leave', 'employee:id,first_name,last_name'])
            ->get();

        $totalAbsences = $absences->count();
        $totalDays = (float) $absences->sum('days_count');

        $byStatus = [
            'pending' => $absences->where('status', 'pending')->count(),
            'approved' => $absences->where('status', 'approved')->count(),
            'rejected' => $absences->where('status', 'rejected')->count(),
        ];

        $byType = $absences->groupBy('absence_type_id')->map(function ($group): array {
            $first = $group->first();

            return [
                'type' => $first?->absenceType->name ?? 'Unknown',
                'code' => $first?->absenceType->code ?? 'unknown',
                'count' => $group->count(),
                'days' => round((float) $group->sum('days_count'), 1),
            ];
        })->values()->all();

        $topEmployees = $absences->groupBy('employee_id')->map(function ($group): array {
            $first = $group->first();
            $employee = $first?->employee;

            return [
                'employee_id' => $first?->employee_id,
                'name' => $employee
                    ? $employee->first_name.' '.$employee->last_name
                    : 'Unknown',
                'count' => $group->count(),
                'days' => round((float) $group->sum('days_count'), 1),
            ];
        })->sortByDesc('days')->take(10)->values()->all();

        return [
            'total_absences' => $totalAbsences,
            'total_days' => round($totalDays, 1),
            'by_status' => $byStatus,
            'by_type' => $byType,
            'top_employees' => $topEmployees,
            'absenteeism_rate' => $this->absenteeismRate($companyId, $from, $to, $totalDays),
        ];
    }

    /**
     * Taux d'absentéisme simplifié : jours d'absence / (jours ouvrés × effectif actif).
     *
     * La table `employees` n'a PAS de colonne `termination_date` (le statut de
     * sortie vit dans `status`, valeurs autorisées : active / suspended /
     * archived / departed — cf. contrainte employees_status_check). Le filtre
     * d'origine provoquait un 500 SQLSTATE[42703] sur CHAQUE appel.
     */
    private function absenteeismRate(string $companyId, string $from, string $to, float $totalDays): float
    {
        $periodDays = Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
        $workingDays = $periodDays * 5 / 7; // Approximation : 5 jours ouvrés par semaine

        $activeEmployees = Employee::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->count();

        if ($activeEmployees === 0 || $workingDays <= 0) {
            return 0.0;
        }

        return round(($totalDays / ($workingDays * $activeEmployees)) * 100, 2);
    }
}
