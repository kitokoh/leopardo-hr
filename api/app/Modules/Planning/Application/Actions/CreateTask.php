<?php

declare(strict_types=1);

namespace App\Modules\Planning\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Planning\Domain\Models\Task;
use App\Modules\Planning\Infrastructure\Services\TaskSchemaService;

/**
 * Cas d'usage : création d'une tâche (manager ou employé pour soi-même).
 *
 * Consommé par `POST /api/v1/tasks` (TaskController::store).
 * L'autorisation est portée par le contrôleur : un non-manager ne peut créer
 * que des tâches auto-affectées (403 sinon) — le payload reçu a déjà été
 * aplati (`assigned_to` = [actor]) par l'appelant dans ce cas.
 */
class CreateTask
{
    public function __construct(
        private readonly TaskSchemaService $taskSchema,
    ) {}

    /**
     * @param  array<string, mixed>  $validated  champs validés (assigned_to aplati par l'interface)
     */
    public function execute(Employee $actor, array $validated): Task
    {
        $task = Task::create($this->taskSchema->filterToExistingColumns([
            'company_id' => $actor->company_id,
            'created_by' => $actor->id,
            'assigned_to' => $validated['assigned_to'] ?? [],
            'status' => 'todo',
            'priority' => $validated['priority'] ?? 'normal',
            'visibility' => $validated['visibility'] ?? 'visible',
            ...$validated,
        ]));

        return $task;
    }
}
