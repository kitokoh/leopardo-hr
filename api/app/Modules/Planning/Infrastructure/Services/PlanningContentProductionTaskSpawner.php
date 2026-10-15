<?php

declare(strict_types=1);

namespace App\Modules\Planning\Infrastructure\Services;

use App\Modules\Planning\Domain\Models\Task;
use App\Shared\Contracts\Marketing\ContentProductionTaskSpawner;
use Throwable;
use Illuminate\Support\Facades\Log;

/**
 * Implémentation Planning du contrat de création de tâche de production
 * marketing (Marketing OS, brief §B).
 *
 * Le module propriétaire de `Task` reste Planning : c'est lui qui connaît
 * ses invariants (statut initial, checklist, visibilité). Le module
 * Marketing ne manipule qu'un identifiant.
 */
final class PlanningContentProductionTaskSpawner implements ContentProductionTaskSpawner
{
    /**
     * @param  array{
     *     title: string,
     *     description: string,
     *     due_date: string,
     *     priority: string,
     *     category: string,
     *     created_by?: int|null,
     * }  $payload
     */
    public function spawn(array $payload): ?int
    {
        try {
            $task = new Task;
            $task->title = $payload['title'];
            $task->description = $payload['description'];
            $task->due_date = $payload['due_date'];
            $task->priority = $payload['priority'];
            $task->category = $payload['category'];
            $task->status = 'pending';
            $task->visibility = 'private';
            $task->assigned_to = [];
            $task->checklist = [];
            $task->created_by = $payload['created_by'] ?? null;
            $task->save();

            return (int) $task->getKey();
        } catch (Throwable $e) {
            // Aucun échec silencieux : l'idée reste promue côté marketing mais
            // l'absence de tâche est tracée pour être rejouée manuellement.
            Log::warning('Marketing OS: création de la tâche de production impossible', [
                'title' => $payload['title'],
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
