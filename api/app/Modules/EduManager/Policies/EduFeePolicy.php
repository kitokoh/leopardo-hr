<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduFee;

/**
 * #5832 (EDU-016) — Policy des frais scolaires.
 *
 * La gestion des frais (création, règlement, remise, annulation) est
 * réservée à la direction (principal/rh/manager) du tenant — données
 * financières scolaires. Un employé lambda ne voit jamais les frais.
 */
class EduFeePolicy
{
    public const MANAGER_ROLES = ['principal', 'rh', 'manager'];

    public function viewAny(Employee $actor): bool
    {
        return $actor->hasManagerRole(...self::MANAGER_ROLES);
    }

    public function view(Employee $actor, EduFee $fee): bool
    {
        return $this->viewAny($actor) && $fee->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function update(Employee $actor, EduFee $fee): bool
    {
        return $this->view($actor, $fee);
    }

    public function delete(Employee $actor, EduFee $fee): bool
    {
        return $this->view($actor, $fee);
    }
}
