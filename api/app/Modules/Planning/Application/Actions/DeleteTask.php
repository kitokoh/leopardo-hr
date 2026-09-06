<?php

declare(strict_types=1);

namespace App\Modules\Planning\Application\Actions;

use App\Modules\Planning\Domain\Models\Task;

/**
 * Cas d'usage : suppression d'une tâche (manager ou créateur).
 *
 * Consommé par `DELETE /api/v1/tasks/{task}` (TaskController::destroy).
 * L'accès est vérifié par le contrôleur (404 cross-tenant, 403 si ni manager
 * ni créateur) avant l'appel.
 */
class DeleteTask
{
    public function execute(Task $task): void
    {
        $task->delete();
    }
}
