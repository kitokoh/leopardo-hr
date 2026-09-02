<?php

declare(strict_types=1);

namespace App\Policies;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Issue #5721 — Politique d'accès au dashboard CRM (pipeline + qualité).
 *
 * Réservé aux rôles commerciaux tenant (principal/rh/marketing) — un
 * comptable, un superviseur ou un employé ordinaire reçoit 403. Les agrégats
 * sont strictement tenant-scoped (company_id injecté dans chaque requête).
 */
class CrmDashboardPolicy
{
    use HandlesAuthorization;

    public function viewDashboard(?Employee $user): bool
    {
        return $user !== null
            && $user->company_id !== null
            && $user->isManager()
            && $user->hasManagerRole('principal', 'rh', 'marketing');
    }
}
