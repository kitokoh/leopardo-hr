<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;

/**
 * RBAC de la vitrine d'un tenant (BC-27 SHOWCASE, #6865).
 *
 * Gestion (création 1-clic, édition, publication/dépublication) réservée au
 * responsable du tenant (sous-rôles `principal`/`rh`) ; lecture ouverte aux
 * membres du tenant (scope `company_id` vérifié). deny-by-default : aucun
 * rôle = refus (fail-closed). La consultation publique n'emprunte pas cette
 * policy — elle passera par un DTO public dédié sur des routes isolées
 * (V-PUBLIC-API #6867).
 */
class CompanyShowcasePolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, CompanyShowcase $showcase): bool
    {
        return $showcase->company_id === (string) $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }

    public function update(Employee $actor, CompanyShowcase $showcase): bool
    {
        return $actor->hasManagerRole('principal', 'rh')
            && $showcase->company_id === (string) $actor->company_id;
    }

    public function delete(Employee $actor, CompanyShowcase $showcase): bool
    {
        return $actor->hasManagerRole('principal', 'rh')
            && $showcase->company_id === (string) $actor->company_id;
    }

    /**
     * Publication / dépublication — même portée que l'édition.
     */
    public function publish(Employee $actor, CompanyShowcase $showcase): bool
    {
        return $this->update($actor, $showcase);
    }
}
