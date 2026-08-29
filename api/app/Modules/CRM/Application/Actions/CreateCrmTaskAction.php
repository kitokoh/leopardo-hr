<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\CRM\Application\DTOs\CreateCrmTaskDTO;
use App\Modules\CRM\Domain\Models\CrmTask;
use Illuminate\Validation\ValidationException;

/**
 * Issue #5720 — Créer une tâche CRM (bornée, tenant-scoped).
 *
 * Gardes : l'assigné doit appartenir au tenant courant (fail-closed
 * cross-tenant), l'account référencé doit exister dans le tenant (404).
 * `company_id` est auto-rempli par le trait BelongsToCompany.
 */
final class CreateCrmTaskAction
{
    public function execute(CreateCrmTaskDTO $dto): CrmTask
    {
        if ($dto->assigneeId !== null && ! $this->assigneeInTenant($dto->assigneeId)) {
            throw ValidationException::withMessages([
                'assigned_to' => ['ASSIGNEE_NOT_IN_TENANT'],
            ]);
        }

        if ($dto->accountId !== null && ! $this->accountInTenant($dto->accountId)) {
            abort(404, 'ACCOUNT_NOT_FOUND');
        }

        /** @var CrmTask $task */
        $task = CrmTask::create([
            'title' => $dto->title,
            'description' => $dto->description,
            'due_at' => $dto->dueAt,
            'status' => 'todo',
            'priority' => $dto->priority,
            'assigned_to' => $dto->assigneeId,
            'account_id' => $dto->accountId,
            'contact_id' => $dto->contactId,
        ]);

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
