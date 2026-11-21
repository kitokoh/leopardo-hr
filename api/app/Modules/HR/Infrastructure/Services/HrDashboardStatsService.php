<?php

declare(strict_types=1);

namespace App\Modules\HR\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Infrastructure\Services\TenantCacheService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agrégations du tableau de bord RH.
 *
 * Extrait de `DashboardController` (360 lignes, dont 6 méthodes privées de
 * comptage) : la couche HTTP ne doit porter que le contrat (parsing de la
 * requête, RBAC, forme de la réponse), pas les règles d'agrégation. Les
 * requêtes ci-dessous étaient auparavant dispersées dans le contrôleur, ce qui
 * rendait toute évolution du périmètre « équipe » (manager_id vs company)
 * impossible à tester sans monter une requête HTTP complète.
 *
 * Le périmètre est TOUJOURS borné par `company_id` : ces méthodes ne prennent
 * pas de tenant implicite, l'appelant fournit l'identifiant de la société de
 * l'acteur authentifié.
 */
class HrDashboardStatsService
{
    public function __construct(
        private readonly TenantCacheService $tenantCache,
    ) {}

    /**
     * Compteurs de synthèse de la société.
     *
     * @return array{
     *     employees_total: int,
     *     employees_active: int,
     *     departments: int,
     *     today_attendance: int,
     *     pending_absences: int
     * }
     */
    public function summary(string $companyId): array
    {
        $employees = DB::table('employees')
            ->where('company_id', $companyId)
            ->selectRaw("count(*) as total, count(case when status = 'active' then 1 end) as active")
            ->first();

        return [
            'employees_total' => $this->intProp($employees, 'total'),
            'employees_active' => $this->intProp($employees, 'active'),
            'departments' => DB::table('departments')
                ->where('company_id', $companyId)
                ->count(),
            'today_attendance' => DB::table('attendance_logs')
                ->where('company_id', $companyId)
                ->whereDate('check_in', now()->toDateString())
                ->count(),
            'pending_absences' => DB::table('absences')
                ->where('company_id', $companyId)
                ->where('status', 'pending')
                ->count(),
        ];
    }

    /**
     * Compteurs du tableau de bord de l'app RH mobile (`GET /api/v1/hr/dashboard`).
     *
     * Extrait de `HrController::dashboard()` : cinq requêtes d'agrégation
     * vivaient dans la couche HTTP, ce qui rendait toute évolution du
     * périmètre (ex. exclure les managers du décompte « actifs ») impossible à
     * tester sans monter une requête HTTP complète.
     *
     * @return array{
     *     total_active_employees: int,
     *     total_employees: int,
     *     pending_invitations: int,
     *     new_this_month: int,
     *     by_contract_type: array<string, int>
     * }
     */
    public function hrDashboard(string $companyId): array
    {
        $byContractType = Employee::where('company_id', $companyId)
            ->whereNotIn('status', ['archived', 'departed'])
            ->selectRaw('contract_type, COUNT(*) as count')
            ->groupBy('contract_type')
            ->pluck('count', 'contract_type');

        /** @var array<string, int> $contractCounts */
        $contractCounts = [];
        foreach ($byContractType as $type => $count) {
            $contractCounts[(string) $type] = is_numeric($count) ? (int) $count : 0;
        }

        return [
            'total_active_employees' => Employee::where('company_id', $companyId)
                ->where('status', 'active')
                ->whereNot('role', 'manager')
                ->count(),
            'total_employees' => Employee::where('company_id', $companyId)
                ->whereNotIn('status', ['archived', 'departed'])
                ->count(),
            'pending_invitations' => Employee::where('company_id', $companyId)
                ->where('status', 'invited')
                ->count(),
            'new_this_month' => Employee::where('company_id', $companyId)
                ->where('status', 'active')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            'by_contract_type' => $contractCounts,
        ];
    }

    /**
     * Digest du manager, mis en cache par (société, manager, jour).
     *
     * @return array<string, mixed>
     */
    public function managerDigest(Employee $manager, string $today): array
    {
        $companyId = (string) $manager->company_id;

        /** @var array<string, mixed> $digest */
        $digest = $this->tenantCache->rememberManagerDigest(
            $companyId,
            $manager->id,
            $today,
            function () use ($manager, $companyId, $today): array {
                $employeeIds = $this->scopedEmployeeIds($manager);

                $teamSize = count($employeeIds);
                $present = $this->countTodayPresent($companyId, $today, $employeeIds);
                $late = $this->countTodayLate($companyId, $today, $employeeIds);
                $openSessions = $this->countOpenSessions($companyId, $today, $employeeIds);
                $pendingAbsences = $this->countPendingByTable('absences', $companyId, $employeeIds);
                $pendingAdvances = $this->countPendingByTable('salary_advances', $companyId, $employeeIds);
                $pendingCorrections = $this->countPendingByTable('attendance_correction_requests', $companyId, $employeeIds);
                $pendingActions = $pendingAbsences + $pendingAdvances + $pendingCorrections;

                return [
                    'date' => $today,
                    'team_scope' => $this->managerTeamScope($manager),
                    'team_size' => $teamSize,
                    'present' => $present,
                    'late' => $late,
                    'open_sessions' => $openSessions,
                    'pending_actions' => $pendingActions,
                    'pending_absences' => $pendingAbsences,
                    'pending_salary_advances' => $pendingAdvances,
                    'pending_corrections' => $pendingCorrections,
                    'items' => [
                        [
                            'kind' => 'attendance',
                            'label' => 'Presences enregistrees',
                            'count' => $present,
                            'severity' => 'success',
                            'route' => '/manager/attendance',
                        ],
                        [
                            'kind' => 'late',
                            'label' => 'Retards a surveiller',
                            'count' => $late,
                            'severity' => $late > 0 ? 'warning' : 'success',
                            'route' => '/manager/anomalies',
                        ],
                        [
                            'kind' => 'actions',
                            'label' => 'Actions RH en attente',
                            'count' => $pendingActions,
                            'severity' => $pendingActions > 0 ? 'info' : 'success',
                            'route' => '/approvals',
                        ],
                    ],
                ];
            }
        );

        return $digest;
    }

    /**
     * Dernières entrées d'audit de la société.
     *
     * @return Collection<int, \stdClass>
     */
    public function recentActivity(string $companyId, int $limit): Collection
    {
        return DB::table('audit_logs')
            ->where('company_id', $companyId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'user_id', 'action', 'auditable_type', 'auditable_id', 'created_at']);
    }

    /**
     * Indicateurs de la période (turnover, embauches, taux d'absentéisme).
     *
     * @return array{
     *     turnover: int,
     *     new_hires: int,
     *     absence_rate: float,
     *     total_active_employees: int
     * }
     */
    public function kpi(string $companyId, Carbon $periodStart, Carbon $periodEnd): array
    {
        $turnover = DB::table('employees')
            ->where('company_id', $companyId)
            ->where('status', 'archived')
            ->whereBetween('updated_at', [$periodStart, $periodEnd])
            ->count();

        $hires = DB::table('employees')
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->count();

        $totalEmployees = DB::table('employees')
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->count();

        $absenceRate = 0.0;
        if ($totalEmployees > 0) {
            $absences = DB::table('absences')
                ->where('company_id', $companyId)
                ->where('status', 'approved')
                ->whereBetween('start_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
                ->count();
            $absenceRate = round(($absences / $totalEmployees) * 100, 1);
        }

        return [
            'turnover' => $turnover,
            'new_hires' => $hires,
            'absence_rate' => $absenceRate,
            'total_active_employees' => $totalEmployees,
        ];
    }

    /**
     * Répartition des effectifs par rôle et rôle manager.
     *
     * @return Collection<int, Employee>
     */
    public function roleCounts(string $companyId): Collection
    {
        return Employee::where('company_id', $companyId)
            ->whereNotIn('status', ['archived', 'departed'])
            ->selectRaw('role, manager_role, COUNT(*) as count')
            ->groupBy('role', 'manager_role')
            ->get();
    }

    /**
     * Identifiants des employés dans le périmètre du manager.
     *
     * Un principal ou un RH voit toute la société ; un manager de proximité ne
     * voit que son équipe directe plus lui-même.
     *
     * @return list<int>
     */
    private function scopedEmployeeIds(Employee $manager): array
    {
        $query = DB::table('employees')
            ->where('company_id', $manager->company_id)
            ->where('status', 'active');

        if (! in_array($manager->manager_role, ['principal', 'rh'], true)) {
            $query->where(function (Builder $scope) use ($manager): void {
                $scope->where('manager_id', $manager->id)
                    ->orWhere('id', $manager->id);
            });
        }

        /** @var list<int> $ids */
        $ids = $query->pluck('id')
            ->map(function (mixed $id): int {
                return is_numeric($id) ? (int) $id : 0;
            })
            ->values()
            ->all();

        return $ids;
    }

    private function managerTeamScope(Employee $manager): string
    {
        return in_array($manager->manager_role, ['principal', 'rh'], true)
            ? 'company'
            : 'managed_team';
    }

    /**
     * @param  list<int>  $employeeIds
     */
    private function countTodayPresent(string $companyId, string $today, array $employeeIds): int
    {
        if ($employeeIds === [] || ! Schema::hasTable('attendance_logs')) {
            return 0;
        }

        return DB::table('attendance_logs')
            ->where('company_id', $companyId)
            ->whereIn('employee_id', $employeeIds)
            ->where('date', $today)
            ->whereNotNull('check_in')
            ->distinct('employee_id')
            ->count('employee_id');
    }

    /**
     * @param  list<int>  $employeeIds
     */
    private function countTodayLate(string $companyId, string $today, array $employeeIds): int
    {
        if ($employeeIds === [] || ! Schema::hasTable('attendance_logs')) {
            return 0;
        }

        return DB::table('attendance_logs')
            ->where('company_id', $companyId)
            ->whereIn('employee_id', $employeeIds)
            ->where('date', $today)
            ->where(function (Builder $query): void {
                $query->where('late_minutes', '>', 0)
                    ->orWhere('status', 'late');
            })
            ->distinct('employee_id')
            ->count('employee_id');
    }

    /**
     * @param  list<int>  $employeeIds
     */
    private function countOpenSessions(string $companyId, string $today, array $employeeIds): int
    {
        if ($employeeIds === [] || ! Schema::hasTable('attendance_logs')) {
            return 0;
        }

        return DB::table('attendance_logs')
            ->where('company_id', $companyId)
            ->whereIn('employee_id', $employeeIds)
            ->where('date', $today)
            ->whereNotNull('check_in')
            ->whereNull('check_out')
            ->count();
    }

    /**
     * @param  list<int>  $employeeIds
     */
    private function countPendingByTable(string $table, string $companyId, array $employeeIds): int
    {
        if ($employeeIds === [] || ! Schema::hasTable($table)) {
            return 0;
        }

        return DB::table($table)
            ->where('company_id', $companyId)
            ->whereIn('employee_id', $employeeIds)
            ->where('status', 'pending')
            ->count();
    }

    /**
     * Lecture d'une propriété entière sur une ligne de requête brute.
     *
     * `DB::table()->first()` renvoie `object|null` : accéder directement à la
     * propriété ferait échouer PHPStan level 8 et masquerait le cas « aucune
     * ligne ». On passe par `get_object_vars` pour rester typé.
     */
    private function intProp(?object $row, string $prop): int
    {
        if ($row === null) {
            return 0;
        }

        $vars = get_object_vars($row);
        $value = $vars[$prop] ?? 0;

        return is_numeric($value) ? (int) $value : 0;
    }
}
