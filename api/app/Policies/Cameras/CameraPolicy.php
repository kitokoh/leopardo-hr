<?php

namespace App\Policies\Cameras;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\EmployeeResourceAssignment;
use App\Modules\Cameras\Domain\Models\Camera;
use App\Modules\Cameras\Domain\Models\CameraAccessToken;
use App\Modules\Cameras\Domain\Models\CameraPermission;

class CameraPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function view(Employee $actor, Camera $camera): bool
    {
        if ($camera->company_id !== $actor->company_id) {
            return false;
        }

        // #7600 — une assignation `camera` (épique #7597) donne la lecture,
        // même à un employé sans rôle manager. Purement additif : sans
        // assignation, le circuit historique (rôles + CameraPermission)
        // s'applique inchangé.
        if ($this->assignedLevelSatisfies($actor, $camera, EmployeeResourceAssignment::LEVEL_VIEW)) {
            return true;
        }

        if (! $actor->isManager()) {
            return false;
        }

        if ($actor->hasManagerRole('principal', 'rh')) {
            return true;
        }

        return $this->activePermission($actor, $camera)?->can_view === true;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal');
    }

    public function update(Employee $actor, Camera $camera): bool
    {
        if ($camera->company_id !== $actor->company_id) {
            return false;
        }

        if ($this->assignedLevelSatisfies($actor, $camera, EmployeeResourceAssignment::LEVEL_MANAGE)) {
            return true;
        }

        if (! $actor->isManager()) {
            return false;
        }

        if ($actor->hasManagerRole('principal')) {
            return true;
        }

        return $this->activePermission($actor, $camera)?->can_manage === true;
    }

    public function delete(Employee $actor, Camera $camera): bool
    {
        return $actor->hasManagerRole('principal') && $camera->company_id === $actor->company_id;
    }

    public function testRtsp(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal');
    }

    public function issueStreamToken(Employee $actor, Camera $camera): bool
    {
        return $this->view($actor, $camera);
    }

    public function shareAccess(Employee $actor, Camera $camera): bool
    {
        if (! $actor->isManager() || $camera->company_id !== $actor->company_id) {
            return false;
        }

        if ($actor->hasManagerRole('principal', 'rh')) {
            return true;
        }

        return $this->activePermission($actor, $camera)?->can_share === true;
    }

    public function revokeAccess(Employee $actor, CameraAccessToken $token): bool
    {
        $camera = $token->camera;

        return $camera instanceof Camera && $this->shareAccess($actor, $camera);
    }

    public function viewLogs(Employee $actor, Camera $camera): bool
    {
        return $this->view($actor, $camera);
    }

    public function managePermissions(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal');
    }

    /**
     * #7600 — niveau d'assignation `camera` du collaborateur, s'il existe
     * (view < operate < manage). `null`/inconnu ne satisfait jamais rien.
     */
    private function assignedLevelSatisfies(Employee $actor, Camera $camera, string $min): bool
    {
        $level = $actor->resourceAssignmentLevel('camera', (int) $camera->id);

        return $level !== null && EmployeeResourceAssignment::satisfies($level, $min);
    }

    private function activePermission(Employee $actor, Camera $camera): ?CameraPermission
    {
        return CameraPermission::query()
            ->where('camera_id', $camera->id)
            ->where('employee_id', $actor->id)
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();
    }
}
