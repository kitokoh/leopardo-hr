<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduStudentGuardian;

/**
 * #5818 (EDU-002) — Policy des relations élève ↔ responsable légal.
 *
 * Réservée à la gestion du tenant (principal/rh/manager) ; bornée au tenant
 * (le lien contient `company_id` — jamais cross-tenant).
 */
class EduStudentGuardianPolicy
{
    public const MANAGER_ROLES = ['principal', 'rh', 'manager'];

    public function viewAny(Employee $actor): bool
    {
        return $actor->hasManagerRole(...self::MANAGER_ROLES);
    }

    public function view(Employee $actor, EduStudentGuardian $link): bool
    {
        return $this->viewAny($actor) && $link->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function update(Employee $actor, EduStudentGuardian $link): bool
    {
        return $this->view($actor, $link);
    }

    public function delete(Employee $actor, EduStudentGuardian $link): bool
    {
        return $this->view($actor, $link);
    }
}
