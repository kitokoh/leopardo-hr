<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Fundraising\Domain\Models\Fundraiser;

/**
 * RBAC des cagnottes d'un tenant (verticale FUNDRAISING — spec §5.2).
 *
 * Gestion (création, édition, publication, clôture, annulation) réservée
 * au responsable du tenant (sous-rôles `principal`/`rh`) — le groupe de
 * routes applique `api.manager:principal,rh` à TOUS les verbes, lecture
 * comprise (pattern showcase : la policy reste le filet sous le
 * middleware ; `view`/`viewAny` vérifient l'appartenance au tenant).
 * deny-by-default. La consultation publique n'emprunte pas cette policy —
 * DTO public dédié sur routes isolées (spec §5.1).
 */
class FundraiserPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, Fundraiser $fundraiser): bool
    {
        return (string) $fundraiser->company_id === (string) $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }

    public function update(Employee $actor, Fundraiser $fundraiser): bool
    {
        return $actor->hasManagerRole('principal', 'rh')
            && (string) $fundraiser->company_id === (string) $actor->company_id;
    }

    public function delete(Employee $actor, Fundraiser $fundraiser): bool
    {
        return $this->update($actor, $fundraiser);
    }

    /** Publication / pause / clôture — même portée que l'édition. */
    public function publish(Employee $actor, Fundraiser $fundraiser): bool
    {
        return $this->update($actor, $fundraiser);
    }
}
