<?php

declare(strict_types=1);

namespace App\Modules\Planning\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Planning\Domain\Models\Task;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;

/**
 * Cas d'usage : mise à jour d'une tâche (manager, créateur ou assigné).
 *
 * Consommé par `PUT|PATCH /api/v1/tasks/{task}` (TaskController::update).
 * Le contrôleur vérifie l'accès (404 cross-tenant, 403 si ni manager, ni
 * créateur, ni assigné) avant l'appel ; l'Action applique ici :
 * - la restriction de champs pour un non-manager (status/completed_*),
 * - la garde de compatibilité des colonnes post-MVP,
 * - les métriques calculées serveur à la complétion (`completed_at`,
 *   `performance_score` — hors $fillable, jamais de mass-assignment),
 * - l'assignation directe de `status` (volontairement exclu du $fillable :
 *   transitions par code explicite, garde SensitiveFillableGuardTest).
 */
class UpdateTask
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(Employee $actor, Task $task, array $validated): Task
    {
        if (! $actor->isManager()) {
            $validated = Arr::only($validated, ['status', 'completed_minutes', 'completion_note']);
        }
        $validated = $this->filterWritableTaskColumns($validated);
        $this->applyCompletionMetrics($task, $validated);

        $task->fill(Arr::except($validated, ['status']));
        if (array_key_exists('status', $validated)) {
            $task->status = $validated['status'];
        }
        $task->save();

        return $task->fresh() ?? $task;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyCompletionMetrics(Task $task, array &$data): void
    {
        if (($data['status'] ?? null) !== 'done') {
            return;
        }

        // Métriques CALCULÉES serveur → assignation explicite, jamais de
        // mass-assignment : `completed_at` et `performance_score` restent
        // hors $fillable (garde SensitiveFillableGuardTest — l'ancien code
        // les injectait dans $data et update() les écartait silencieusement :
        // performance_score restait null).
        $task->completed_at = $task->completed_at ?? now('UTC');
        unset($data['completed_at']);

        $estimated = (int) ($data['estimated_minutes'] ?? $task->estimated_minutes ?? 0);
        $completed = (int) ($data['completed_minutes'] ?? $task->completed_minutes ?? 0);
        if ($estimated > 0 && $completed > 0) {
            $ratio = max(0.0, min(2.0, $estimated / $completed));
            $task->performance_score = round($ratio * 50, 2);
        }
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
