<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Pharmacy\Domain\Models\PharmacyPublicProfile;

/**
 * RBAC du profil public d'officine (#9616).
 *
 * ## Pourquoi une policy dédiée
 *
 * Publier une officine dans l'annuaire grand public n'est pas une opération
 * opérationnelle : c'est un acte de **configuration de tenant**, au même titre
 * que l'activation de la boutique en ligne Retail (`RetailOnlineSettingsPolicy`,
 * #9207). Un préparateur ne doit pas pouvoir exposer l'officine au public.
 *
 * deny-by-default : aucun rôle manager = refus. La lecture reste ouverte aux
 * membres du tenant (le comptoir doit pouvoir vérifier ce qui est publié), mais
 * toujours bornée au `company_id` de l'acteur.
 */
class PharmacyPublicProfilePolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, PharmacyPublicProfile $profile): bool
    {
        return $profile->company_id === (string) $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function update(Employee $actor, PharmacyPublicProfile $profile): bool
    {
        return $actor->isManager() && $profile->company_id === (string) $actor->company_id;
    }
}
