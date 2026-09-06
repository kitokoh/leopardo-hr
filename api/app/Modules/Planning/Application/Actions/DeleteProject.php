<?php

declare(strict_types=1);

namespace App\Modules\Planning\Application\Actions;

use App\Modules\Planning\Domain\Models\Project;

/**
 * Cas d'usage : suppression d'un projet par un manager.
 *
 * Consommé par `DELETE /api/v1/projects/{project}`
 * (ProjectController::destroy). L'appartenance au tenant et le rôle manager
 * sont vérifiés par le contrôleur (404/403) avant l'appel.
 */
class DeleteProject
{
    public function execute(Project $project): void
    {
        $project->delete();
    }
}
