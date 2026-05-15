<?php

namespace App\AI;

use App\AI\DTOs\AIResponse;
use App\AI\DTOs\ToolCall;
use App\AI\DTOs\ToolResult;
use App\Models\Department;
use App\Models\Employee;

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
            if ($this->writeToolPolicy->requiresConfirmation($toolCall->name)) {
                return $this->pendingConfirmationResult($toolCall, $companyId, $userId);
            }

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
        return match ($toolName) {
            'create_absence' => sprintf(
                'Creer une demande d\'absence du %s au %s',
                $arguments['start_date'] ?? '?',
                $arguments['end_date'] ?? ($arguments['start_date'] ?? '?'),
            ),
            'approve_absence' => sprintf(
                'Approuver l\'absence #%s',
                $arguments['absence_id'] ?? '?',
            ),
            default => "Confirmer l'action {$toolName}",
        };
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

        $limit = min((int) ($args['limit'] ?? 20), 50);
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
        $search = $args['query'] ?? '';

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
}
