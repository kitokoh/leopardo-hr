<?php

declare(strict_types=1);

namespace App\Modules\Planning\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Planning\Domain\Models\Project;

/**
 * Cas d'usage : création d'un projet par un manager.
 *
 * Consommé par `POST /api/v1/projects` (ProjectController::store).
 * Les membres et le statut par défaut (`active`) sont portés par l'Action ;
 * la validation (dont l'existence des membres dans le tenant) reste dans le
 * contrôleur.
 */
class CreateProject
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(Employee $actor, array $validated): Project
    {
        return Project::create([
            'company_id' => $actor->company_id,
            'created_by' => $actor->id,
            'members' => $validated['members'] ?? [],
            'status' => $validated['status'] ?? 'active',
            ...$validated,
        ]);
    }
}
