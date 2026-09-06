<?php

declare(strict_types=1);

namespace App\Modules\Planning\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Planning\Domain\Models\Task;
use Illuminate\Support\Facades\Schema;

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
    /**
     * @param  array<string, mixed>  $validated  champs validés (assigned_to aplati par l'interface)
     */
    public function execute(Employee $actor, array $validated): Task
    {
        $task = Task::create($this->filterWritableTaskColumns([
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

    /**
     * Colonnes ajoutées post-MVP : ignorées si la table ne les porte pas
     * encore (compatibilité montée de schéma progressive, garde historique).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function filterWritableTaskColumns(array $data): array
    {
        foreach (['category', 'checklist', 'visibility'] as $column) {
            if (array_key_exists($column, $data) && ! Schema::hasColumn('tasks', $column)) {
                unset($data[$column]);
            }
        }

        return $data;
    }
}
