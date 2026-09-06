<?php

declare(strict_types=1);

namespace App\Modules\Planning\Application\Actions;

use App\Modules\Planning\Domain\Models\Project;

/**
 * Cas d'usage : mise à jour d'un projet par un manager.
 *
 * Consommé par `PUT|PATCH /api/v1/projects/{project}`
 * (ProjectController::update). L'appartenance au tenant et le rôle manager
 * sont vérifiés par le contrôleur (404/403) avant l'appel.
 */
class UpdateProject
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(Project $project, array $validated): Project
    {
        $project->update($validated);

        return $project->fresh() ?? $project;
    }
}
