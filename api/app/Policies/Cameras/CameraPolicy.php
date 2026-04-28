<?php

namespace App\Policies\Cameras;

use App\Models\Employee;
use App\Modules\Cameras\Domain\Camera;
use App\Modules\Cameras\Domain\CameraAccessToken;
use App\Modules\Cameras\Domain\CameraPermission;

class CameraPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function view(Employee $actor, Camera $camera): bool
    {
        if (! $actor->isManager() || $camera->company_id !== $actor->company_id) {
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
        if (! $actor->isManager() || $camera->company_id !== $actor->company_id) {
            return false;
        }

        if ($actor->hasManagerRole('principal')) {
            return true;
        }

        return $this->activePermission($actor, $camera)?->can_manage === true;
    }

    public function delete(Employee $actor, Camera $camera): bool
    {
        return $this->update($actor, $camera);
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

        if ($actor->hasManagerRole('principal')) {
            return true;
        }

        return $this->activePermission($actor, $camera)?->can_share === true;
    }

    public function revokeAccess(Employee $actor, CameraAccessToken $token): bool
    {
        $camera = $token->camera;

        return $camera instanceof Camera && $this->shareAccess($actor, $camera);
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
