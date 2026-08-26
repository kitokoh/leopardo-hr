<?php

declare(strict_types=1);

namespace App\AI;

use App\AI\DTOs\AIResponse;
use App\AI\DTOs\ToolCall;
use App\AI\DTOs\ToolResult;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\HR\Domain\Models\Department;
use App\Modules\Notification\Domain\Models\AppNotification;
use App\Modules\Payroll\Domain\Models\Payroll;
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
        ];
    }

    /**
     * @return array<int, ToolResult>
     */
    public function executeToolCalls(AIResponse $response, string $companyId, int $userId): array
    {
        $results = [];

        foreach ($response->toolCalls as $toolCall) {
            $results[] = $this->executeSingleTool($toolCall, $companyId, $userId);
        }

        return $results;
    }

    private function executeSingleTool(ToolCall $toolCall, string $companyId, int $userId): ToolResult
    {
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
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function dispatchToolAction(string $toolName, array $arguments, string $companyId, int $userId): array
    {
        return match ($toolName) {
            'get_employees' => $this->getEmployees($companyId, $arguments),
            'get_employee_details' => $this->getEmployeeDetails($companyId, $arguments),
            'get_departments' => $this->getDepartments($companyId),
            'get_headcount' => $this->getHeadcount($companyId),
            'search_employees' => $this->searchEmployees($companyId, $arguments),
            'get_attendance_today' => $this->getAttendanceToday($companyId, $userId),
            'get_attendance_anomalies' => $this->getAttendanceAnomalies($companyId, $arguments),
            'get_monthly_report' => $this->getMonthlyReport($companyId),
            'get_absences' => $this->getAbsences($companyId, $userId, $arguments),
            'get_daily_summary' => $this->getDailySummary($companyId),
            'get_notifications' => $this->getNotifications($companyId, $userId, $arguments),
            'get_leave_balances' => $this->getLeaveBalances($companyId, $userId),
            'get_payroll_summary' => $this->getPayrollSummary($companyId, $userId),
            default => ['message' => "Tool '{$toolName}' is registered but not yet implemented."],
        };
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function getEmployees(string $companyId, array $args): array
    {
        $query = Employee::where('company_id', $companyId);

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
    private function getEmployeeDetails(string $companyId, array $args): array
    {
        $employee = Employee::where('company_id', $companyId)
            ->where('id', $args['employee_id'] ?? 0)
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
    private function searchEmployees(string $companyId, array $args): array
    {
        $query = Employee::where('company_id', $companyId);
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
    private function getDailySummary(string $companyId): array
    {
        $today = Carbon::today()->toDateString();

        $checkins = AttendanceLog::where('company_id', $companyId)
            ->whereDate('date', $today)
            ->count();

        $onLeave = Absence::where('company_id', $companyId)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->where('status', 'approved')
            ->distinct('employee_id')
            ->count('employee_id');

        return [
            'date' => $today,
            'checkins' => $checkins,
            'employees_on_leave' => $onLeave,
        ];
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
}
