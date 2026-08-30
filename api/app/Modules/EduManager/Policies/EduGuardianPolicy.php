<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduGuardian;

/**
 * #5818 (EDU-002) — Policy des responsables légaux.
 *
 * Gestion du tenant : accès complet borné au tenant. Un gardien ne peut
 * consulter que SON propre profil (lien `employee_id`), jamais les profils
 * d'autres responsables (données PII).
 */
class EduGuardianPolicy
{
    public const MANAGER_ROLES = ['principal', 'rh', 'manager'];

    public function viewAny(Employee $actor): bool
    {
        return $actor->hasManagerRole(...self::MANAGER_ROLES);
    }

    public function view(Employee $actor, EduGuardian $guardian): bool
    {
        if ($this->viewAny($actor)) {
            return $guardian->company_id === $actor->company_id;
        }

        // Un gardien ne voit que son propre profil.
        return $guardian->company_id === $actor->company_id
            && $guardian->employee_id === $actor->id;
    }

    public function create(Employee $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function update(Employee $actor, EduGuardian $guardian): bool
    {
        return $this->viewAny($actor) && $guardian->company_id === $actor->company_id;
    }

    public function delete(Employee $actor, EduGuardian $guardian): bool
    {
        return $this->update($actor, $guardian);
    }
}
