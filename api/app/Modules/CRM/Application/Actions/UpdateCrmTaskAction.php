<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\CRM\Application\DTOs\UpdateCrmTaskDTO;
use App\Modules\CRM\Domain\Models\CrmTask;
use Illuminate\Validation\ValidationException;

/**
 * Issue #5720 — Mettre à jour une tâche CRM (champs bornés, transitions
 * de statut contrôlées).
 */
final class UpdateCrmTaskAction
{
    /** @var array<string, string> */
    private const STATUS_TRANSITIONS = [
        'todo' => ['in_progress', 'done', 'cancelled'],
        'in_progress' => ['todo', 'done', 'cancelled'],
        'done' => ['todo', 'in_progress'],
        'cancelled' => ['todo'],
    ];

    public function execute(CrmTask $task, UpdateCrmTaskDTO $dto): CrmTask
    {
        if ($dto->assigneeId !== null && ! $this->assigneeInTenant($dto->assigneeId)) {
            throw ValidationException::withMessages([
                'assigned_to' => ['ASSIGNEE_NOT_IN_TENANT'],
            ]);
        }

        if ($dto->accountId !== null && ! $this->accountInTenant($dto->accountId)) {
            abort(404, 'ACCOUNT_NOT_FOUND');
        }

        $fields = [
            'title' => $dto->title,
            'description' => $dto->description,
            'due_at' => $dto->dueAt,
            'priority' => $dto->priority,
            'assigned_to' => $dto->assigneeId,
            'account_id' => $dto->accountId,
            'contact_id' => $dto->contactId,
        ];

        foreach ($fields as $field => $value) {
            if ($value !== null) {
                $task->{$field} = $value;
            }
        }

        if ($dto->status !== null) {
            $allowed = self::STATUS_TRANSITIONS[$task->status] ?? [];

            if (! in_array($dto->status, $allowed, true)) {
                throw ValidationException::withMessages([
                    'status' => ['INVALID_TASK_STATUS_TRANSITION'],
                ]);
            }

            $task->status = $dto->status;
        }

        $task->save();

        return $task;
    }

    private function assigneeInTenant(int $assigneeId): bool
    {
        /** @var Employee|null $assignee */
        $assignee = Employee::query()->find($assigneeId);

        return $assignee !== null && $assignee->company_id === currentCompany()?->id;
    }

    private function accountInTenant(int $accountId): bool
    {
        return app(\App\Modules\CRM\Domain\Models\CrmAccount::class)
            ->newQuery()
            ->whereKey($accountId)
            ->exists();
    }
}
