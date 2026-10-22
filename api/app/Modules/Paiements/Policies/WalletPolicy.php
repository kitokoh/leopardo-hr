<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Paiements\Domain\Models\Wallet;

/**
 * BC-38 PAIEMENTS (#8711) — autorisation explicite sur le wallet.
 *
 * **Perimetre par defaut, en attente de confirmation produit.** Le BC-38 ne
 * disposait d'aucune policy : l'autorisation reposait entierement sur le
 * middleware `tenant` + le scope `BelongsToCompany`. Ce fichier rend le
 * perimetre explicite et testable, avec la repartition suivante :
 *
 *   - lecture (solde, historique, commissions) : `principal` et `rh` —
 *     fonctions de pilotage/paye qui ont besoin de voir les flux ;
 *   - demande de retrait : `principal` uniquement — c'est le seul role qui
 *     engage le compte bancaire du tenant ;
 *   - surface plateforme (Superadmin) : inchangee, elle passe par le guard
 *     `super_admin_api` + `platform.permission:payments.*`, et n'appelle donc
 *     jamais ces policies.
 *
 * Cette repartition est une **decision produit par defaut** ; elle est
 * documentee comme telle dans la PR et peut etre ajustee sans toucher au
 * domaine (la policy est le seul point a modifier).
 *
 * Fail-closed : un role `employee`, un `manager` sans `manager_role`, ou un
 * employe d'un autre tenant n'obtient rien.
 */
class WalletPolicy
{
    /**
     * Le role peut-il voir le wallet d'un tenant ?
     */
    public function view(Employee $actor, Wallet $wallet): bool
    {
        return $this->belongsToActorTenant($actor, $wallet)
            && ($actor->isPrincipal() || $actor->isHr());
    }

    /**
     * Le role peut-il demander un retrait sur ce wallet ?
     */
    public function requestCashOut(Employee $actor, Wallet $wallet): bool
    {
        return $this->belongsToActorTenant($actor, $wallet)
            && $actor->isPrincipal();
    }

    /**
     * Isolation inter-tenant : le wallet doit appartenir a la company de
     * l'acteur. Compare en chaine pour rester insensible au typage de la cle
     * (uuid/string selon l'environnement de test).
     */
    private function belongsToActorTenant(Employee $actor, Wallet $wallet): bool
    {
        $actorCompany = $this->stringId($actor->company_id);
        $walletCompany = $this->stringId($wallet->company_id);

        return $actorCompany !== null
            && $walletCompany !== null
            && $actorCompany === $walletCompany;
    }

    private function stringId(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_scalar($value) ? (string) $value : null;
    }
}
