<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Middleware\Trade\TradeRoleResolver;
use App\Modules\Trade\Domain\Models\TradeCompanyProfile;

/**
 * RBAC du profil commercial Trade (BC-36, #9617).
 *
 * ## Pourquoi `admin` et pas `sales`
 *
 * Le profil commercial décrit l'ENTREPRISE (directions import/export, marchés,
 * devise, Incoterm) et son opt-in dans l'annuaire public. Ce n'est pas une
 * opération commerciale : un chargé d'affaires ne doit pas pouvoir publier
 * l'entreprise dans l'annuaire grand public, ni changer sa devise par défaut.
 *
 * `TradeRoleResolver` est la source de vérité unique des rôles (leçon #8185) :
 * on le consomme, on ne réimplémente pas la matrice.
 *
 * deny-by-default : aucun rôle `admin` = refus.
 */
class TradeCompanyProfilePolicy
{
    public function __construct(private readonly TradeRoleResolver $roles = new TradeRoleResolver) {}

    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, TradeCompanyProfile $profile): bool
    {
        return $profile->company_id === (string) $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $this->roles->hasRole($actor, 'admin');
    }

    public function update(Employee $actor, TradeCompanyProfile $profile): bool
    {
        return $profile->company_id === (string) $actor->company_id
            && $this->roles->hasRole($actor, 'admin');
    }
}
