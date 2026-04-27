<?php

namespace App\Policies\Cameras;

use App\Models\Employee;
use App\Modules\Cameras\Domain\Camera;
use App\Modules\Cameras\Domain\CameraAccessToken;

class CameraPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function view(Employee $actor, Camera $camera): bool
    {
        if ($actor->hasManagerRole('principal', 'rh')) {
            return true;
        }

        if (! $actor->isManager()) {
            return false;
        }

        return $camera->permissions()
            ->where('employee_id', $actor->id)
            ->where('can_view', true)
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal');
    }

    public function update(Employee $actor, Camera $camera): bool
    {
        if ($actor->hasManagerRole('principal')) {
            return true;
        }

        if (! $actor->isManager()) {
            return false;
        }

        return $camera->permissions()
            ->where('employee_id', $actor->id)
            ->where('can_manage', true)
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    public function delete(Employee $actor, Camera $camera): bool
    {
        return $actor->hasManagerRole('principal');
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
        if ($actor->hasManagerRole('principal', 'rh')) {
            return true;
        }

        if (! $actor->isManager()) {
            return false;
        }

        return $camera->permissions()
            ->where('employee_id', $actor->id)
            ->where('can_share', true)
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    public function revokeAccess(Employee $actor, CameraAccessToken $token): bool
    {
        return $this->shareAccess($actor, $token->camera);
    }

    public function viewLogs(Employee $actor, Camera $camera): bool
    {
        return $this->view($actor, $camera);
    }

    public function managePermissions(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal');
    }
}
