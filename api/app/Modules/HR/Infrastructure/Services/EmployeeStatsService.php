<?php

declare(strict_types=1);

namespace App\Modules\HR\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Agrégations de l'effectif (`GET /employees/stats`).
 *
 * Même découpage que `AbsenceStatsService` / `HrDashboardStatsService` : la
 * couche HTTP ne porte que le contrat (parsing, RBAC, forme de la réponse),
 * les comptages vivent ici et sont testables sans monter une requête HTTP.
 *
 * Le périmètre est TOUJOURS borné par `company_id` fourni par l'appelant, et
 * par le périmètre « équipe » de l'acteur quand il est team-scoped
 * (`visibleToManager`) : un superviseur ne doit pas lire les effectifs d'un
 * autre département via les statistiques alors qu'il ne voit pas ces employés
 * dans la liste.
 */
class EmployeeStatsService
{
    /**
     * @return array{
     *     total: int,
     *     by_status: array<string, int>,
     *     by_role: array<string, int>,
     *     by_manager_role: array<string, int>,
     *     by_contract_type: array<string, int>,
     *     by_department: array<int, array{id: int|null, name: string, count: int}>,
     *     headcount: array{active: int, archived: int, suspended: int},
     *     contracts_expiring: array{in_30_days: int, in_60_days: int, in_90_days: int},
     *     payroll: array{currency: string|null, monthly_base_total: float, average_base: float, headcount_with_salary: int},
     *     seniority: array{average_months: float, new_hires_90_days: int}
     * }
     */
    public function overview(Employee $actor, ?string $from = null, ?string $to = null): array
    {
        $query = $this->scopedQuery($actor);

        if ($from !== null) {
            $query->where('contract_start', '>=', $from);
        }

        if ($to !== null) {
            $query->where('contract_start', '<=', $to);
        }

        /** @var \Illuminate\Support\Collection<int, Employee> $employees */
        $employees = $query->get([
            'id',
            'status',
            'role',
            'manager_role',
            'contract_type',
            'contract_start',
            'contract_end',
            'department_id',
            'salary_base',
            'salary_type',
        ]);

        $today = Carbon::now()->startOfDay();

        $byStatus = $employees->groupBy(fn (Employee $e): string => (string) ($e->status ?? 'unknown'))
            ->map(fn ($group): int => $group->count())
            ->all();

        $byRole = $employees->groupBy(fn (Employee $e): string => (string) ($e->role ?? 'unknown'))
            ->map(fn ($group): int => $group->count())
            ->all();

        $byManagerRole = $employees
            ->filter(fn (Employee $e): bool => $e->manager_role !== null && $e->manager_role !== '')
            ->groupBy(fn (Employee $e): string => (string) $e->manager_role)
            ->map(fn ($group): int => $group->count())
            ->all();

        $byContractType = $employees->groupBy(fn (Employee $e): string => (string) ($e->contract_type ?? 'unknown'))
            ->map(fn ($group): int => $group->count())
            ->all();

        $departmentNames = $this->departmentNames($employees);

        $byDepartment = $employees
            ->groupBy(fn (Employee $e): string => (string) ($e->department_id ?? 'none'))
            ->map(function ($group, $key) use ($departmentNames): array {
                $id = $key === 'none' ? null : (int) $key;

                return [
                    'id' => $id,
                    'name' => $id === null ? 'Sans département' : ($departmentNames[$id] ?? 'Département #'.$id),
                    'count' => $group->count(),
                ];
            })
            ->sortByDesc('count')
            ->values()
            ->all();

        $active = $employees->where('status', 'active');

        $expiring = static function (int $days) use ($active, $today): int {
            $limit = $today->copy()->addDays($days)->toDateString();

            return $active->filter(static function (Employee $e) use ($today, $limit): bool {
                if ($e->contract_end === null) {
                    return false;
                }

                $end = Carbon::parse($e->contract_end)->toDateString();

                return $end >= $today->toDateString() && $end <= $limit;
            })->count();
        };

        // Seuls les salaires MENSUELS sont additionnables : un horaire ou un
        // journalier mélangé à un mensuel produirait un total qui ne veut rien
        // dire. On expose donc le nombre de contrats réellement pris en compte.
        // #eval-2026-11-23 — `salary_type` vaut `fixed` pour un salaire
        // mensuel (enum DB fixed|hourly|daily). Filtrer sur `monthly`
        // renvoyait toujours 0 : le total de masse salariale etait faux.
        // Un `salary_base` a 0 (valeur par defaut de la colonne) n'est pas un
        // salaire : il ne doit ni peser dans la moyenne ni gonfler l'effectif
        // « avec salaire ».
        $monthly = $active->filter(static fn (Employee $e): bool => $e->salary_type === 'fixed'
            && $e->salary_base !== null
            && (float) $e->salary_base > 0.0);

        $monthlyTotal = (float) $monthly->sum(fn (Employee $e): float => (float) $e->salary_base);

        $seniorityMonths = $active
            ->filter(static fn (Employee $e): bool => $e->contract_start !== null)
            ->map(static fn (Employee $e): float => Carbon::parse($e->contract_start)->diffInMonths($today));

        return [
            'total' => $employees->count(),
            'by_status' => $byStatus,
            'by_role' => $byRole,
            'by_manager_role' => $byManagerRole,
            'by_contract_type' => $byContractType,
            'by_department' => $byDepartment,
            'headcount' => [
                'active' => (int) ($byStatus['active'] ?? 0),
                'archived' => (int) ($byStatus['archived'] ?? 0),
                'suspended' => (int) ($byStatus['suspended'] ?? 0),
            ],
            'contracts_expiring' => [
                'in_30_days' => $expiring(30),
                'in_60_days' => $expiring(60),
                'in_90_days' => $expiring(90),
            ],
            'payroll' => [
                'currency' => $this->currency($actor),
                'monthly_base_total' => round($monthlyTotal, 2),
                'average_base' => $monthly->isEmpty() ? 0.0 : round($monthlyTotal / $monthly->count(), 2),
                'headcount_with_salary' => $monthly->count(),
            ],
            'seniority' => [
                'average_months' => $seniorityMonths->isEmpty() ? 0.0 : round((float) $seniorityMonths->avg(), 1),
                'new_hires_90_days' => $active->filter(static fn (Employee $e): bool => $e->contract_start !== null
                    && Carbon::parse($e->contract_start)->gte($today->copy()->subDays(90)))->count(),
            ],
        ];
    }

    /**
     * @return Builder<Employee>
     */
    private function scopedQuery(Employee $actor): Builder
    {
        $query = Employee::query()->where('company_id', $actor->company_id);

        if ($actor->isTeamScoped()) {
            // Fail closed : un acteur team-scoped sans périmètre assigné voit
            // un effectif vide plutôt que celui de toute la société.
            $query->visibleToManager($actor);
        }

        return $query;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Employee>  $employees
     * @return array<int, string>
     */
    private function departmentNames($employees): array
    {
        $ids = $employees->pluck('department_id')->filter()->unique()->values()->all();

        if ($ids === []) {
            return [];
        }

        return \Illuminate\Support\Facades\DB::table('departments')
            ->whereIn('id', $ids)
            ->pluck('name', 'id')
            ->all();
    }

    private function currency(Employee $actor): ?string
    {
        $currency = \Illuminate\Support\Facades\DB::table('companies')
            ->where('id', $actor->company_id)
            ->value('currency');

        return is_string($currency) ? $currency : null;
    }
}
