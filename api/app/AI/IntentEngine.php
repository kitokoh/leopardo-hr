<?php

declare(strict_types=1);

namespace App\AI;

use App\AI\DTOs\AIResponse;
use App\AI\DTOs\ToolCall;
use App\AI\DTOs\ToolResult;
use App\AI\Exceptions\ToolPermissionDeniedException;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\HR\Domain\Models\Department;
use App\Modules\Notification\Domain\Models\AppNotification;
use App\Modules\Payroll\Domain\Models\Payroll;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Domain\Models\LeaveBalance;
use Illuminate\Support\Carbon;

class IntentEngine
{
    public function __construct(
        private readonly ToolRegistry $toolRegistry,
        private readonly WriteToolPolicy $writeToolPolicy,
        private readonly PendingActionStore $pendingActionStore,
        private readonly WriteActionRunner $writeActionRunner,
        // BC-23-D05 (issue #6237) : matrice de permissions par outil AI.
        private readonly ToolPermissionPolicy $toolPermissionPolicy,
    ) {}

    /**
     * @return array<int, ToolResult>
     */
    /**
     * Issue #5625 : liste statique des outils read qui ont un handler PHP.
     * Utilisée par l'Orchestrator pour filtrer le registre DB avant d'exposer
     * les outils au LLM — un outil enregistré en DB mais absent ici ne sera
     * JAMAIS proposé à l'utilisateur.
     *
     * @return list<string>
     */
    public static function supportedReadToolNames(): array
    {
        return [
            'get_employees',
            'get_employee_details',
            'get_departments',
            'get_headcount',
            'search_employees',
            'get_attendance_today',
            'get_attendance_anomalies',
            'get_monthly_report',
            'get_absences',
            'get_daily_summary',
            'get_notifications',
            'get_leave_balances',
            'get_payroll_summary',
            // B1 (#6854) — outils lecture BC-04 HR (contrat A3 #6850).
            'team_overview',
            'team_absences_recent',
            'employee_leave_balance',
            // B2 (#6855) — outil lecture BC-07 PAYROLL (contrat A3 #6850).
            'payroll_current_status',
        ];
    }

    /**
     * @return array<int, ToolResult>
     */
    public function executeToolCalls(AIResponse $response, string $companyId, int $userId): array
    {
        // BC-23-D05 : le rôle est résolu une seule fois pour toute la boucle
        // de tool calls (évite N requêtes Employee).
        $role = $this->toolPermissionPolicy->resolveRole($userId, $companyId);
        $results = [];

        foreach ($response->toolCalls as $toolCall) {
            $results[] = $this->executeSingleTool($toolCall, $companyId, $userId, $role);
        }

        return $results;
    }

    private function executeSingleTool(ToolCall $toolCall, string $companyId, int $userId, string $role): ToolResult
    {
        // BC-23-D05 : fail-closed — l'appel d'un outil hors matrice (rôle ou
        // permission insuffisante) est refusé AVANT tout effet de bord
        // (y compris la création d'une pending action pour les write-tools).
        try {
            $this->toolPermissionPolicy->assertCanUse($toolCall->name, $role);
        } catch (ToolPermissionDeniedException $exception) {
            return new ToolResult(
                toolCallId: $toolCall->id,
                name: $toolCall->name,
                content: json_encode([
                    'error' => $exception->errorCode(),
                    'message' => 'AI tool permission denied',
                ]) ?: '{}',
                success: false,
            );
        }

        if ($this->writeToolPolicy->requiresConfirmation($toolCall->name)) {
            return $this->pendingConfirmationResult($toolCall, $companyId, $userId);
        }

        $tool = $this->toolRegistry->findTool($toolCall->name);

        if ($tool === null) {
            return new ToolResult(
                toolCallId: $toolCall->id,
                name: $toolCall->name,
                content: json_encode(['error' => "Unknown tool: {$toolCall->name}"]) ?: '{}',
                success: false,
            );
        }

        try {
            $result = $this->dispatchToolAction($toolCall->name, $toolCall->arguments, $companyId, $userId);

            return new ToolResult(
                toolCallId: $toolCall->id,
                name: $toolCall->name,
                content: json_encode($result) ?: '{}',
                success: true,
            );
        } catch (\Throwable $e) {
            return new ToolResult(
                toolCallId: $toolCall->id,
                name: $toolCall->name,
                content: json_encode(['error' => $e->getMessage()]) ?: '{}',
                success: false,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function executeConfirmedWrite(string $toolName, array $arguments, string $companyId, int $userId): array
    {
        if (! $this->writeToolPolicy->requiresConfirmation($toolName)) {
            return ['error' => "Tool '{$toolName}' does not require confirmation."];
        }

        // BC-23-D05 : défense en profondeur — le rôle est re-vérifié à la
        // confirmation (le rôle du demandeur peut avoir changé entre le chat
        // et le clic de confirmation).
        $role = $this->toolPermissionPolicy->resolveRole($userId, $companyId);
        if (! $this->toolPermissionPolicy->canUse($toolName, $role)) {
            return [
                'error' => 'AI_TOOL_PERMISSION_DENIED',
                'code' => ToolPermissionDeniedException::ERROR_CODE,
            ];
        }

        return $this->writeActionRunner->run($toolName, $arguments, $companyId, $userId);
    }

    private function pendingConfirmationResult(ToolCall $toolCall, string $companyId, int $userId): ToolResult
    {
        $pendingId = $this->pendingActionStore->store(
            $companyId,
            $userId,
            $toolCall->name,
            $toolCall->arguments,
        );

        $payload = [
            'status' => 'confirmation_required',
            'pending_action_id' => $pendingId,
            'tool' => $toolCall->name,
            'summary' => $this->confirmationSummary($toolCall->name, $toolCall->arguments),
            'arguments' => $toolCall->arguments,
        ];

        return new ToolResult(
            toolCallId: $toolCall->id,
            name: $toolCall->name,
            content: json_encode($payload) ?: '{}',
            success: true,
        );
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function confirmationSummary(string $toolName, array $arguments): string
    {
        $startDate = $this->stringArgument($arguments, 'start_date', '?');
        $endDate = $this->stringArgument($arguments, 'end_date', $startDate);
        $absenceId = $this->stringArgument($arguments, 'absence_id', '?');

        return match ($toolName) {
            'create_absence' => sprintf(
                'Creer une demande d\'absence du %s au %s',
                $startDate,
                $endDate,
            ),
            'approve_absence' => sprintf(
                'Approuver l\'absence #%s',
                $absenceId,
            ),
            default => "Confirmer l'action {$toolName}",
        };
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function stringArgument(array $arguments, string $key, string $default): string
    {
        if (! array_key_exists($key, $arguments)) {
            return $default;
        }

        $value = $arguments[$key];

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function intArgument(array $arguments, string $key, int $default): int
    {
        if (! array_key_exists($key, $arguments)) {
            return $default;
        }

        $value = $arguments[$key];

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * Issue #5625 : une SEULE source de vérité pour les read-tools — la map
     * ci-dessous sert à la fois au dispatch et au test de couverture
     * (ToolRegistryCoverageTest). Tout outil du registre (ai_tool_registry)
     * SANS entrée ici échoue en CI : plus de promesse « je peux X » sans
     * handler.
     *
     * @return array<string, callable(array<string, mixed>): array<string, mixed>>
     */
    private function readToolHandlers(string $companyId, int $userId): array
    {
        return [
            'get_employees' => function (array $arguments) use ($companyId, $userId): array {
                /** @var array<string, mixed> $arguments */
                return $this->getEmployees($companyId, $userId, $arguments);
            },
            'get_employee_details' => function (array $arguments) use ($companyId, $userId): array {
                /** @var array<string, mixed> $arguments */
                return $this->getEmployeeDetails($companyId, $userId, $arguments);
            },
            'get_departments' => fn (array $arguments): array => $this->getDepartments($companyId),
            'get_headcount' => fn (array $arguments): array => $this->getHeadcount($companyId),
            'search_employees' => function (array $arguments) use ($companyId, $userId): array {
                /** @var array<string, mixed> $arguments */
                return $this->searchEmployees($companyId, $userId, $arguments);
            },
            'get_attendance_today' => fn (array $arguments): array => $this->getAttendanceToday($companyId, $userId),
            'get_attendance_anomalies' => function (array $arguments) use ($companyId): array {
                /** @var array<string, mixed> $arguments */
                return $this->getAttendanceAnomalies($companyId, $arguments);
            },
            'get_monthly_report' => fn (array $arguments): array => $this->getMonthlyReport($companyId),
            'get_absences' => function (array $arguments) use ($companyId, $userId): array {
                /** @var array<string, mixed> $arguments */
                return $this->getAbsences($companyId, $userId, $arguments);
            },
            'get_daily_summary' => fn (array $arguments): array => $this->getDailySummary($companyId, $userId),
            'get_notifications' => function (array $arguments) use ($companyId, $userId): array {
                /** @var array<string, mixed> $arguments */
                return $this->getNotifications($companyId, $userId, $arguments);
            },
            'get_leave_balances' => fn (array $arguments): array => $this->getLeaveBalances($companyId, $userId),
            'get_payroll_summary' => fn (array $arguments): array => $this->getPayrollSummary($companyId, $userId),
            // B1 (#6854) — outils lecture BC-04 HR (contrat A3 #6850) :
            // agrégats via les modèles canoniques HR/Planning.
            'team_overview' => fn (array $arguments): array => $this->getTeamOverview($companyId, $userId),
            'team_absences_recent' => function (array $arguments) use ($companyId, $userId): array {
                /** @var array<string, mixed> $arguments */
                return $this->getTeamAbsencesRecent($companyId, $userId, $arguments);
            },
            'employee_leave_balance' => function (array $arguments) use ($companyId, $userId): array {
                /** @var array<string, mixed> $arguments */
                return $this->getEmployeeLeaveBalance($companyId, $userId, $arguments);
            },
            // B2 (#6855) — agrégat du statut de paie via les modèles canoniques
            // Payroll (PayrollRun + PaySlip) — aucun montant exposé.
            'payroll_current_status' => fn (array $arguments): array => $this->getPayrollCurrentStatus($companyId, $userId),
        ];
    }

    /**
     * Noms des read-tools effectivement dispatchables (issue #5625).
     *
     * @return list<string>
     */
    public function supportedReadTools(): array
    {
        return array_keys($this->readToolHandlers('', 0));
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function dispatchToolAction(string $toolName, array $arguments, string $companyId, int $userId): array
    {
        $handler = $this->readToolHandlers($companyId, $userId)[$toolName] ?? null;
        if ($handler === null) {
            return ['message' => "Tool '{$toolName}' is registered but not yet implemented."];
        }

        return $handler($arguments);
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function getEmployees(string $companyId, int $userId, array $args): array
    {
        $query = Employee::where('company_id', $companyId);

        // #6532 — défense en profondeur : un acteur non-manager ne reçoit
        // que ses propres données (le gate principal est la matrice
        // ai.tool_permissions, rôle manager pour cet outil).
        if (! $this->actorIsManager($companyId, $userId)) {
            $query->where('id', $userId);
        }

        if (isset($args['status'])) {
            $query->where('status', $args['status']);
        }

        if (isset($args['department_id'])) {
            $query->where('department_id', $args['department_id']);
        }

        $limit = min($this->intArgument($args, 'limit', 20), 50);
        $employees = $query->select(['id', 'first_name', 'last_name', 'email', 'post', 'department_id', 'status'])
            ->limit($limit)
            ->get();

        return ['employees' => $employees->toArray(), 'count' => $employees->count()];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function getEmployeeDetails(string $companyId, int $userId, array $args): array
    {
        // #6532 — non-manager : uniquement son propre profil (pas de fuite
        // d'existence sur les autres employés).
        $requestedId = $this->intArgument($args, 'employee_id', 0);
        if (! $this->actorIsManager($companyId, $userId) && $requestedId !== $userId) {
            return ['error' => 'Employee not found'];
        }

        $employee = Employee::where('company_id', $companyId)
            ->where('id', $requestedId)
            ->select(['id', 'first_name', 'last_name', 'email', 'post', 'department_id', 'status', 'phone', 'hire_date'])
            ->first();

        if (! $employee) {
            return ['error' => 'Employee not found'];
        }

        return ['employee' => $employee->toArray()];
    }

    /**
     * @return array<string, mixed>
     */
    private function getDepartments(string $companyId): array
    {
        $departments = Department::where('company_id', $companyId)
            ->select(['id', 'name'])
            ->get();

        return ['departments' => $departments->toArray()];
    }

    /**
     * @return array<string, mixed>
     */
    private function getHeadcount(string $companyId): array
    {
        $total = Employee::where('company_id', $companyId)->count();
        $active = Employee::where('company_id', $companyId)->where('status', 'active')->count();

        return ['total' => $total, 'active' => $active, 'inactive' => $total - $active];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function searchEmployees(string $companyId, int $userId, array $args): array
    {
        $query = Employee::where('company_id', $companyId);

        // #6532 — non-manager : recherche bornée à soi-même.
        if (! $this->actorIsManager($companyId, $userId)) {
            $query->where('id', $userId);
        }

        $search = $this->stringArgument($args, 'query', '');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('post', 'ilike', "%{$search}%");
            });
        }

        $employees = $query->select(['id', 'first_name', 'last_name', 'email', 'post', 'status'])
            ->limit(20)
            ->get();

        return ['employees' => $employees->toArray(), 'count' => $employees->count()];
    }

    /**
     * @return array<string, mixed>
     */
    private function getAttendanceToday(string $companyId, int $userId): array
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
    private function getAttendanceAnomalies(string $companyId, array $args): array
    {
        $days = min(max($this->intArgument($args, 'days', 7), 1), 30);
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
    private function getMonthlyReport(string $companyId): array
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
    private function getAbsences(string $companyId, int $userId, array $args): array
    {
        $query = Absence::where('company_id', $companyId);

        if ($this->stringArgument($args, 'scope', 'self') === 'team') {
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

        $limit = min($this->intArgument($args, 'limit', 10), 50);
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
    private function getDailySummary(string $companyId, int $userId): array
    {
        $today = Carbon::today()->toDateString();

        // #6532 — non-manager : agrégats restreints à soi-même.
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
     * #6532 — un acteur non-manager (employé) ne voit que ses propres
     * données sur les outils PII (défense en profondeur, par-dessus la
     * matrice ai.tool_permissions qui exige déjà le rôle manager).
     */
    private function actorIsManager(string $companyId, int $userId): bool
    {
        return $this->toolPermissionPolicy->resolveRole($userId, $companyId) === 'manager';
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function getNotifications(string $companyId, int $userId, array $args): array
    {
        // L'employé doit appartenir au tenant appelant (garde isolation §II).
        if (! Employee::where('company_id', $companyId)->where('id', $userId)->exists()) {
            return ['notifications' => [], 'count' => 0];
        }

        $limit = min($this->intArgument($args, 'limit', 10), 50);
        $onlyUnread = filter_var($this->stringArgument($args, 'unread_only', 'false'), FILTER_VALIDATE_BOOLEAN);

        $query = AppNotification::where('user_id', $userId);

        if ($onlyUnread) {
            $query->where('read', false);
        }

        $notifications = $query->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'type', 'title', 'body', 'read', 'created_at']);

        $mapped = $notifications->map(function (AppNotification $notification) {
            return [
                'id' => $notification->id,
                'title' => $notification->title,
                'body' => $notification->body,
                'read' => (bool) $notification->read,
                'created_at' => $notification->created_at?->toIso8601String(),
            ];
        });

        return ['notifications' => $mapped->values()->toArray(), 'count' => $mapped->count()];
    }

    /**
     * @return array<string, mixed>
     */
    private function getLeaveBalances(string $companyId, int $userId): array
    {
        $balances = LeaveBalance::where('company_id', $companyId)
            ->where('employee_id', $userId)
            ->orderBy('absence_type_id')
            ->get(['id', 'absence_type_id', 'balance', 'used', 'pending', 'year']);

        return ['leave_balances' => $balances->toArray(), 'count' => $balances->count()];
    }

    /**
     * @return array<string, mixed>
     */
    private function getPayrollSummary(string $companyId, int $userId): array
    {
        $payrolls = Payroll::where('company_id', $companyId)
            ->where('employee_id', $userId)
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->limit(6)
            ->get(['id', 'period_month', 'period_year', 'gross_salary', 'net_salary', 'status', 'validated_at']);

        return ['payrolls' => $payrolls->toArray(), 'count' => $payrolls->count()];
    }

    /**
     * B1 (#6854) — agrégats d'effectif du périmètre du manager (entreprise
     * pour les rôles company-wide, département pour `dept`, équipe directe
     * pour `superviseur` — scope canonique Employee::visibleToManager()).
     * Sortie AGRÉGÉE uniquement : aucune donnée nominative (privacy A6,
     * #6853) ; la liste nominative reste l'apanage du REST HR.
     *
     * @return array<string, mixed>
     */
    private function getTeamOverview(string $companyId, int $userId): array
    {
        /** @var Employee|null $actor */
        $actor = Employee::where('company_id', $companyId)->find($userId);

        if ($actor === null) {
            return ['error' => 'Employee not found'];
        }

        // Défense en profondeur (#6532) : un non-manager (jamais atteint via
        // la matrice ai.tool_permissions, rôle manager requis) ne voit que
        // lui-même — parité avec les outils PII existants.
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
            'by_status' => $employees->countBy('status')->all(),
            'by_contract_type' => $employees->countBy('contract_type')->all(),
            'by_department' => $byDepartment,
        ];
    }

    /**
     * B1 (#6854) — absences récentes du périmètre du manager sur une période
     * (chevauchement start/end, défaut 30 derniers jours), agrégées par
     * statut. Sortie non nominative : identifiants employés uniquement, pas
     * de motif (raison) ni de données brutes sensibles (privacy A6, #6853).
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function getTeamAbsencesRecent(string $companyId, int $userId, array $args): array
    {
        /** @var Employee|null $actor */
        $actor = Employee::where('company_id', $companyId)->find($userId);

        if ($actor === null) {
            return ['error' => 'Employee not found'];
        }

        $from = $this->stringArgument($args, 'from', Carbon::today()->subDays(30)->toDateString());
        $to = $this->stringArgument($args, 'to', Carbon::today()->toDateString());

        $query = Absence::where('company_id', $companyId)
            ->where('start_date', '<=', $to)
            ->where('end_date', '>=', $from);

        if ($actor->isManager()) {
            // Périmètre canonique du manager (visibleToManager, fails closed).
            $teamIds = Employee::query()
                ->where('company_id', $companyId)
                ->whereNotIn('status', ['archived', 'departed'])
                ->visibleToManager($actor)
                ->pluck('id');
            $query->whereIn('employee_id', $teamIds);
        } else {
            // Défense en profondeur (#6532) : un non-manager ne voit que ses
            // propres absences (matrice : rôle manager requis).
            $query->where('employee_id', $userId);
        }

        $status = $this->stringArgument($args, 'status', '');
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
     * B1 (#6854) — soldes de congés d'un employé (snapshot canonique
     * LeaveBalance du module Planning, propriétaire PA2-ARCH-002) pour une
     * année. Un employé ne consulte que son propre solde (#6532) ; un
     * manager consulte tout employé du tenant (parité LeavePolicyController).
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function getEmployeeLeaveBalance(string $companyId, int $userId, array $args): array
    {
        /** @var Employee|null $actor */
        $actor = Employee::where('company_id', $companyId)->find($userId);

        if ($actor === null) {
            return ['error' => 'Employee not found'];
        }

        $requestedId = $this->intArgument($args, 'employee_id', $userId);
        $year = $this->intArgument($args, 'year', (int) now()->format('Y'));

        // #6532 — pas de fuite d'existence : un non-manager qui demande le
        // solde d'un autre employé reçoit « not found », pas un refus bavard.
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

    /**
     * B2 (#6855) — statut agrégé de la paie du tenant (BC-07 PAYROLL).
     *
     * Réutilise les modèles canoniques `PayrollRun`/`PaySlip` (contrat
     * PayrollRunController) : dernier run clôturé (validé/payé/verrouillé) et
     * run en cours éventuel (draft/calculating/processing/calculated/error)
     * avec progression — bulletins générés/validés rapportés à l'effectif du
     * run. Sortie AGRÉGÉE : statuts, dates et compteurs uniquement — jamais de
     * montants (nominatifs ou totaux), jamais de bulletins (privacy A6, #6853).
     * Lecteur : manager RH/principal (matrice ai.tool_permissions, rôle
     * manager + payroll.view) — fail-closed sinon (#6532).
     *
     * @return array<string, mixed>
     */
    private function getPayrollCurrentStatus(string $companyId, int $userId): array
    {
        /** @var Employee|null $actor */
        $actor = Employee::where('company_id', $companyId)->find($userId);

        if ($actor === null) {
            return ['error' => 'Employee not found'];
        }

        // #6532 — défense en profondeur : un non-manager (jamais atteint via la
        // matrice ai.tool_permissions) n'accède pas au statut de paie.
        if (! $actor->isManager()) {
            return ['error' => 'Employee not found'];
        }

        /** @var array<int, PayrollRun> $runs */
        $runs = PayrollRun::query()
            ->where('company_id', $companyId)
            ->where('status', '!=', PayrollRun::STATUS_CANCELLED)
            ->orderByDesc('period_end')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        $currentRun = null;
        $lastClosedRun = null;

        foreach ($runs as $run) {
            if (in_array($run->status, [
                PayrollRun::STATUS_DRAFT,
                PayrollRun::STATUS_CALCULATING,
                PayrollRun::STATUS_PROCESSING,
                PayrollRun::STATUS_CALCULATED,
                PayrollRun::STATUS_ERROR,
            ], true) && $currentRun === null) {
                $currentRun = $run;
            }

            if (in_array($run->status, [
                PayrollRun::STATUS_VALIDATED,
                PayrollRun::STATUS_PAID,
                PayrollRun::STATUS_LOCKED,
            ], true) && $lastClosedRun === null) {
                $lastClosedRun = $run;
            }

            if ($currentRun !== null && $lastClosedRun !== null) {
                break;
            }
        }

        return [
            'has_current_run' => $currentRun !== null,
            'current_run' => $currentRun !== null ? $this->payrollRunStatusPayload($currentRun) : null,
            'last_closed_run' => $lastClosedRun !== null ? $this->payrollRunStatusPayload($lastClosedRun) : null,
            'as_of' => now()->toIso8601String(),
        ];
    }

    /**
     * Payload agrégé d'un run : identité temporelle, statut, effectif et
     * compteurs de bulletins — explicitement AUCUN montant (privacy A6,
     * #6853). `validated` = bulletins au statut `validated` (scope canonique
     * PaySlip::scopeValidated).
     *
     * @return array<string, mixed>
     */
    private function payrollRunStatusPayload(PayrollRun $run): array
    {
        $slipsCount = PaySlip::query()->where('payroll_run_id', $run->id)->count();
        $validatedSlipsCount = PaySlip::query()
            ->where('payroll_run_id', $run->id)
            ->where('status', 'validated')
            ->count();

        return [
            'id' => $run->id,
            'status' => $run->status,
            'period' => [
                'start' => $run->period_start?->toDateString(),
                'end' => $run->period_end?->toDateString(),
            ],
            'employee_count' => $run->employee_count,
            'slips_count' => $slipsCount,
            'validated_slips_count' => $validatedSlipsCount,
            'calculated_at' => $run->calculated_at?->toIso8601String(),
            'validated_at' => $run->validated_at?->toIso8601String(),
            'paid_at' => $run->paid_at?->toIso8601String(),
            'updated_at' => $run->updated_at?->toIso8601String(),
        ];
    }
}
