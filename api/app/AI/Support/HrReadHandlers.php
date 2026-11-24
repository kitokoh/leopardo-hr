<?php

declare(strict_types=1);

namespace App\AI\Support;

use App\AI\ToolPermissionPolicy;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\HR\Domain\Models\Department;
use App\Modules\Notification\Domain\Models\Notification;
use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Domain\Models\LeaveBalance;
use Illuminate\Support\Carbon;

/**
 * Handlers de lecture RH de l'assistant — extraits de `IntentEngine` par #9887.
 *
 * ## Ce que cette classe ne fait PAS
 *
 * Elle ne decide pas si l'outil est autorise : la matrice `ai.tool_permissions`
 * est verifiee en amont par `IntentEngine` (fail-closed, avant tout effet de
 * bord). Elle ne valide pas les schemas : c'est `ToolSchemaGuard`. Elle
 * EXECUTE, et rien d'autre.
 *
 * ## La defense en profondeur est conservee telle quelle
 *
 * Chaque handler qui expose des donnees nominatives re-verifie le role de
 * l'acteur (`actorIsManager`) et borne la requete a `company_id`. Ce n'est pas
 * de la redondance inutile : la matrice peut autoriser un outil a un role qui
 * ne devrait voir que ses propres donnees, et un `company_id` resolu depuis la
 * session ne doit jamais etre remplace par un argument du LLM. Ces gardes
 * etaient dispersees dans 1 375 lignes ; elles sont ici au meme endroit que
 * les requetes qu'elles protegent.
 *
 * ## Contrainte de #9887 : zero changement de comportement
 *
 * Chaque methode est un deplacement mecanique. Les commentaires d'origine qui
 * portaient une decision (et non une description) sont conserves : ils
 * expliquent pourquoi une colonne est selectionnee, pourquoi un tri est
 * explicite, pourquoi un non-manager recoit « not found » plutot qu'un refus
 * bavard.
 */
final class HrReadHandlers
{
    public function __construct(
        private readonly ToolPermissionPolicy $toolPermissionPolicy,
        private readonly ToolArgumentReader $arguments,
    ) {}

    /**
     * #6532 — un acteur non-manager (employe) ne voit que ses propres donnees
     * sur les outils PII (defense en profondeur, par-dessus la matrice
     * ai.tool_permissions qui exige deja le role manager).
     */
    public function actorIsManager(string $companyId, int $userId): bool
    {
        return $this->toolPermissionPolicy->resolveRole($userId, $companyId) === 'manager';
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function getEmployees(string $companyId, int $userId, array $args): array
    {
        $query = Employee::where('company_id', $companyId);

        // #6532 — defense en profondeur : un acteur non-manager ne recoit
        // que ses propres donnees (le gate principal est la matrice
        // ai.tool_permissions, role manager pour cet outil).
        if (! $this->actorIsManager($companyId, $userId)) {
            $query->where('id', $userId);
        }

        if (isset($args['status'])) {
            $query->where('status', $args['status']);
        }

        if (isset($args['department_id'])) {
            $query->where('department_id', $args['department_id']);
        }

        $limit = min($this->arguments->int($args, 'limit', 20), 50);
        // #7356 — `post` n'existe pas sur `employees` (le schema porte
        // `position_id`) : la selection levait SQLSTATE[42703] a chaque appel.
        $employees = $query->select(['id', 'first_name', 'last_name', 'email', 'position_id', 'department_id', 'status'])
            ->limit($limit)
            ->get();

        return ['employees' => $employees->toArray(), 'count' => $employees->count()];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function getEmployeeDetails(string $companyId, int $userId, array $args): array
    {
        // #6532 — non-manager : uniquement son propre profil (pas de fuite
        // d'existence sur les autres employes).
        $requestedId = $this->arguments->int($args, 'employee_id', 0);
        if (! $this->actorIsManager($companyId, $userId) && $requestedId !== $userId) {
            return ['error' => 'Employee not found'];
        }

        // #7356 — `post` et `hire_date` n'existent pas sur `employees` : le
        // schema porte `position_id` et `contract_start` (meme mapping que
        // EmployeeResource::hire_date, cf. GenerateTurnoverReportAction).
        $employee = Employee::where('company_id', $companyId)
            ->where('id', $requestedId)
            ->select(['id', 'first_name', 'last_name', 'email', 'position_id', 'department_id', 'status', 'phone', 'contract_start'])
            ->first();

        if (! $employee) {
            return ['error' => 'Employee not found'];
        }

        return ['employee' => $employee->toArray()];
    }

    /**
     * @return array<string, mixed>
     */
    public function getDepartments(string $companyId): array
    {
        $departments = Department::where('company_id', $companyId)
            ->select(['id', 'name'])
            ->get();

        return ['departments' => $departments->toArray()];
    }

    /**
     * @return array<string, mixed>
     */
    public function getHeadcount(string $companyId): array
    {
        $total = Employee::where('company_id', $companyId)->count();
        $active = Employee::where('company_id', $companyId)->where('status', 'active')->count();

        return ['total' => $total, 'active' => $active, 'inactive' => $total - $active];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function searchEmployees(string $companyId, int $userId, array $args): array
    {
        $query = Employee::where('company_id', $companyId);

        // #6532 — non-manager : recherche bornee a soi-meme.
        if (! $this->actorIsManager($companyId, $userId)) {
            $query->where('id', $userId);
        }

        $search = $this->arguments->string($args, 'query', '');

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    // #7356 — l'intitule de poste vit dans `extra_data.job_title`
                    // (JSONB) et dans le catalogue `positions` ; la colonne
                    // `post` n'existe pas sur `employees`.
                    ->orWhere('extra_data->job_title', 'ilike', "%{$search}%")
                    ->orWhereHas('position', fn ($position) => $position->where('name', 'ilike', "%{$search}%"));
            });
        }

        $employees = $query->select(['id', 'first_name', 'last_name', 'email', 'position_id', 'status'])
            ->limit(20)
            ->get();

        return ['employees' => $employees->toArray(), 'count' => $employees->count()];
    }

    /**
     * @return array<string, mixed>
     */
    public function getAttendanceToday(string $companyId, int $userId): array
    {
        $today = Carbon::today()->toDateString();

        $logs = AttendanceLog::where('company_id', $companyId)
            ->where('employee_id', $userId)
            ->whereDate('date', $today)
            ->orderByDesc('date')
            ->limit(10)
            ->get(['id', 'employee_id', 'date', 'check_in', 'check_out', 'status']);

        $companyCount = AttendanceLog::where('company_id', $companyId)
            ->whereDate('date', $today)
            ->count();

        return [
            'employee_id' => $userId,
            'date' => $today,
            'logs' => $logs->toArray(),
            'company_checkins' => $companyCount,
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function getAttendanceAnomalies(string $companyId, array $args): array
    {
        $days = min(max($this->arguments->int($args, 'days', 7), 1), 30);
        $since = Carbon::today()->subDays($days);

        $logs = AttendanceLog::where('company_id', $companyId)
            ->whereDate('date', '>=', $since->toDateString())
            ->where(function ($query) {
                $query->whereNull('check_out')
                    ->orWhereTime('check_in', '>', '09:00:00');
            })
            ->orderByDesc('date')
            ->limit(50)
            ->get(['id', 'employee_id', 'date', 'check_in', 'check_out', 'status']);

        return ['anomalies' => $logs->toArray(), 'count' => $logs->count(), 'since' => $since->toDateString()];
    }

    /**
     * @return array<string, mixed>
     */
    public function getMonthlyReport(string $companyId): array
    {
        $monthStart = Carbon::now()->startOfMonth();

        $totalLogs = AttendanceLog::where('company_id', $companyId)
            ->whereDate('date', '>=', $monthStart->toDateString())
            ->count();

        $onTime = AttendanceLog::where('company_id', $companyId)
            ->whereDate('date', '>=', $monthStart->toDateString())
            ->whereTime('check_in', '<=', '09:00:00')
            ->count();

        $activeEmployees = AttendanceLog::where('company_id', $companyId)
            ->whereDate('date', '>=', $monthStart->toDateString())
            ->distinct('employee_id')
            ->count('employee_id');

        return [
            'month' => $monthStart->format('Y-m'),
            'attendance_logs' => $totalLogs,
            'on_time_checkins' => $onTime,
            'active_employees' => $activeEmployees,
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function getAbsences(string $companyId, int $userId, array $args): array
    {
        $query = Absence::where('company_id', $companyId);

        if ($this->arguments->string($args, 'scope', 'self') === 'team') {
            $teamIds = Employee::where('company_id', $companyId)
                ->where('manager_id', $userId)
                ->pluck('id');
            $query->where(function ($q) use ($teamIds, $userId) {
                $q->where('employee_id', $userId)
                    ->orWhereIn('employee_id', $teamIds);
            });
        } else {
            $query->where('employee_id', $userId);
        }

        $limit = min($this->arguments->int($args, 'limit', 10), 50);
        $absences = $query->with('absenceType')
            ->orderByDesc('start_date')
            ->limit($limit)
            ->get(['id', 'employee_id', 'absence_type_id', 'start_date', 'end_date', 'status', 'reason']);

        $mapped = $absences->map(function (Absence $absence) {
            return [
                'id' => $absence->id,
                'employee_id' => $absence->employee_id,
                'type' => $absence->absenceType?->name,
                'start_date' => $absence->start_date->toDateString(),
                'end_date' => $absence->end_date?->toDateString(),
                'status' => $absence->status,
                'reason' => $absence->reason,
            ];
        });

        return ['absences' => $mapped->values()->toArray(), 'count' => $mapped->count()];
    }

    /**
     * @return array<string, mixed>
     */
    public function getDailySummary(string $companyId, int $userId): array
    {
        $today = Carbon::today()->toDateString();

        // #6532 — non-manager : agregats restreints a soi-meme.
        $checkins = AttendanceLog::where('company_id', $companyId)
            ->whereDate('date', $today);

        $onLeave = Absence::where('company_id', $companyId)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->where('status', 'approved');

        if (! $this->actorIsManager($companyId, $userId)) {
            $checkins->where('employee_id', $userId);
            $onLeave->where('employee_id', $userId);
        }

        return [
            'date' => $today,
            'checkins' => $checkins->count(),
            'employees_on_leave' => $onLeave->distinct('employee_id')->count('employee_id'),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function getNotifications(string $companyId, int $userId, array $args): array
    {
        // L'employe doit appartenir au tenant appelant (garde isolation §II).
        if (! Employee::where('company_id', $companyId)->where('id', $userId)->exists()) {
            return ['notifications' => [], 'count' => 0];
        }

        $limit = min($this->arguments->int($args, 'limit', 10), 50);
        $onlyUnread = filter_var($this->arguments->string($args, 'unread_only', 'false'), FILTER_VALIDATE_BOOLEAN);

        // #7481 — l'assistant lit le MEME store que `GET /notifications`
        // (`notifications`), sinon il annoncait des notifications que la boite
        // de reception de l'utilisateur n'affichait pas — et inversement.
        $query = Notification::where('employee_id', $userId);

        if ($onlyUnread) {
            $query->where('is_read', false);
        }

        $notifications = $query->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'type', 'title', 'body', 'is_read', 'created_at']);

        $mapped = $notifications->map(function (Notification $notification) {
            return [
                'id' => $notification->id,
                'title' => $notification->title,
                'body' => $notification->body,
                'read' => (bool) $notification->is_read,
                'created_at' => $notification->created_at?->toIso8601String(),
            ];
        });

        return ['notifications' => $mapped->values()->toArray(), 'count' => $mapped->count()];
    }

    /**
     * @return array<string, mixed>
     */
    public function getLeaveBalances(string $companyId, int $userId): array
    {
        $balances = LeaveBalance::where('company_id', $companyId)
            ->where('employee_id', $userId)
            ->orderBy('absence_type_id')
            ->get(['id', 'absence_type_id', 'balance', 'used', 'pending', 'year']);

        return ['leave_balances' => $balances->toArray(), 'count' => $balances->count()];
    }

    /**
     * B1 (#6854) — agregats d'effectif du perimetre du manager (entreprise
     * pour les roles company-wide, departement pour `dept`, equipe directe
     * pour `superviseur` — scope canonique Employee::visibleToManager()).
     * Sortie AGREGEE uniquement : aucune donnee nominative (privacy A6,
     * #6853) ; la liste nominative reste l'apanage du REST HR.
     *
     * @return array<string, mixed>
     */
    public function getTeamOverview(string $companyId, int $userId): array
    {
        /** @var Employee|null $actor */
        $actor = Employee::where('company_id', $companyId)->find($userId);

        if ($actor === null) {
            return ['error' => 'Employee not found'];
        }

        // Defense en profondeur (#6532) : un non-manager (jamais atteint via
        // la matrice ai.tool_permissions, role manager requis) ne voit que
        // lui-meme — parite avec les outils PII existants.
        $query = Employee::query()
            ->where('company_id', $companyId)
            ->whereNotIn('status', ['archived', 'departed']);

        if ($actor->isManager()) {
            $query->visibleToManager($actor);
        } else {
            $query->where('id', $userId);
        }

        $employees = $query->with('department:id,name')
            ->get(['id', 'department_id', 'contract_type', 'status']);

        $byDepartment = [];
        foreach ($employees->groupBy('department_id') as $group) {
            $byDepartment[] = [
                'department' => $group->first()?->department?->name,
                'count' => $group->count(),
            ];
        }
        usort($byDepartment, static fn (array $a, array $b): int => strcmp(
            (string) ($a['department'] ?? ''),
            (string) ($b['department'] ?? ''),
        ));

        return [
            'scope' => ! $actor->isManager() ? 'self' : ($actor->isTeamScoped() ? 'team' : 'company'),
            'total' => $employees->count(),
            // Tri deterministe des agregats (#8261) : countBy() conserve
            // l'ordre de premiere occurrence, qui depend du plan d'execution
            // Postgres (aucun ORDER BY sur la requete source) — la sortie
            // JSON de l'outil devait donc varier d'un appel a l'autre.
            'by_status' => $employees->countBy('status')->sortKeys()->all(),
            'by_contract_type' => $employees->countBy('contract_type')->sortKeys()->all(),
            'by_department' => $byDepartment,
        ];
    }

    /**
     * B1 (#6854) — absences recentes du perimetre du manager sur une periode
     * (chevauchement start/end, defaut 30 derniers jours), agregees par
     * statut. Sortie non nominative : identifiants employes uniquement, pas
     * de motif (raison) ni de donnees brutes sensibles (privacy A6, #6853).
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function getTeamAbsencesRecent(string $companyId, int $userId, array $args): array
    {
        /** @var Employee|null $actor */
        $actor = Employee::where('company_id', $companyId)->find($userId);

        if ($actor === null) {
            return ['error' => 'Employee not found'];
        }

        $from = $this->arguments->string($args, 'from', Carbon::today()->subDays(30)->toDateString());
        $to = $this->arguments->string($args, 'to', Carbon::today()->toDateString());

        $query = Absence::where('company_id', $companyId)
            ->where('start_date', '<=', $to)
            ->where('end_date', '>=', $from);

        if ($actor->isManager()) {
            // Perimetre canonique du manager (visibleToManager, fails closed).
            $teamIds = Employee::query()
                ->where('company_id', $companyId)
                ->whereNotIn('status', ['archived', 'departed'])
                ->visibleToManager($actor)
                ->pluck('id');
            $query->whereIn('employee_id', $teamIds);
        } else {
            // Defense en profondeur (#6532) : un non-manager ne voit que ses
            // propres absences (matrice : role manager requis).
            $query->where('employee_id', $userId);
        }

        $status = $this->arguments->string($args, 'status', '');
        if ($status !== '') {
            $query->where('status', $status);
        }

        $total = (clone $query)->count();
        $byStatus = (clone $query)->select('status')->get()->countBy('status')->all();

        $absences = $query->with('absenceType:id,name')
            ->orderByDesc('start_date')
            ->limit(20)
            ->get(['id', 'employee_id', 'absence_type_id', 'start_date', 'end_date', 'days_count', 'status']);

        $mapped = $absences->map(fn (Absence $absence): array => [
            'id' => $absence->id,
            'employee_id' => $absence->employee_id,
            'type' => $absence->absenceType?->name,
            'start_date' => $absence->start_date->toDateString(),
            'end_date' => $absence->end_date?->toDateString(),
            'days_count' => $absence->days_count,
            'status' => $absence->status,
        ]);

        return [
            'period' => ['from' => $from, 'to' => $to],
            'total' => $total,
            'count' => $mapped->count(),
            'by_status' => $byStatus,
            'absences' => $mapped->values()->toArray(),
        ];
    }

    /**
     * B1 (#6854) — soldes de conges d'un employe (snapshot canonique
     * LeaveBalance du module Planning, proprietaire PA2-ARCH-002) pour une
     * annee. Un employe ne consulte que son propre solde (#6532) ; un
     * manager consulte tout employe du tenant (parite LeavePolicyController).
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function getEmployeeLeaveBalance(string $companyId, int $userId, array $args): array
    {
        /** @var Employee|null $actor */
        $actor = Employee::where('company_id', $companyId)->find($userId);

        if ($actor === null) {
            return ['error' => 'Employee not found'];
        }

        $requestedId = $this->arguments->int($args, 'employee_id', $userId);
        $year = $this->arguments->int($args, 'year', (int) now()->format('Y'));

        // #6532 — pas de fuite d'existence : un non-manager qui demande le
        // solde d'un autre employe recoit « not found », pas un refus bavard.
        if (! $actor->isManager() && $requestedId !== $userId) {
            return ['error' => 'Employee not found'];
        }

        if ($requestedId !== $userId
            && ! Employee::where('company_id', $companyId)->where('id', $requestedId)->exists()) {
            return ['error' => 'Employee not found'];
        }

        $balances = LeaveBalance::query()
            ->where('company_id', $companyId)
            ->where('employee_id', $requestedId)
            ->forYear($year)
            ->with('absenceType:id,name')
            ->orderBy('absence_type_id')
            ->get(['id', 'absence_type_id', 'balance', 'used', 'pending', 'year']);

        $mapped = $balances->map(fn (LeaveBalance $balance): array => [
            'absence_type' => $balance->absenceType?->name,
            'balance' => $balance->balance,
            'used' => $balance->used,
            'pending' => $balance->pending,
            'year' => $balance->year,
        ]);

        return [
            'employee_id' => $requestedId,
            'year' => $year,
            'count' => $mapped->count(),
            'leave_balances' => $mapped->values()->toArray(),
        ];
    }
}
