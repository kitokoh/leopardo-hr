<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Paiements\Domain\Models\PaymentProviderInstance;

/**
 * BC-38 PAIEMENTS (#8711) — autorisation explicite sur une instance de
 * fournisseur.
 *
 * Particularite du modele : `company_id` est **nullable** (NULL = instance
 * globale de la plateforme). Un tenant ne doit donc jamais pouvoir lire ni
 * modifier une instance globale, ni celle d'un autre tenant : la policy
 * l'interdit explicitement, et l'egalite de `company_id` est *stricte* (deux
 * `null` ne suffisent pas — sinon tout tenant verrait les instances globales).
 *
 * La creation/pilotage des instances est reservee a la surface Superadmin
 * (`platform.permission:payments.*`) ; cote tenant, cette policy n'ouvre que
 * la **lecture** de son instance dediee.
 */
class PaymentProviderInstancePolicy
{
    public function view(Employee $actor, PaymentProviderInstance $instance): bool
    {
        return $this->isDedicatedToActorTenant($actor, $instance)
            && ($actor->isPrincipal() || $actor->isHr());
    }

    public function update(Employee $actor, PaymentProviderInstance $instance): bool
    {
        // Aucune surface tenant n'ecrit une instance : la configuration est
        // plateforme. Cette methode existe pour rendre l'intention explicite
        // et empecher un futur controleur de l'ouvrir par accident.
        return false;
    }

    private function isDedicatedToActorTenant(Employee $actor, PaymentProviderInstance $instance): bool
    {
        $actorCompany = $this->stringId($actor->company_id);
        $instanceCompany = $this->stringId($instance->company_id);

        // Instance globale (null) => jamais accessible a un tenant.
        return $actorCompany !== null
            && $instanceCompany !== null
            && $actorCompany === $instanceCompany;
    }

    private function stringId(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_scalar($value) ? (string) $value : null;
    }
}
