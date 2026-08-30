<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Delivery\Domain\Models\Delivery;
use App\Modules\Delivery\Domain\Models\DeliveryStop;

/**
 * Policy des livraisons (BC-26-D05, issue #6294).
 *
 * Deny-by-default : seuls les rôles delivery.dispatcher / delivery.admin
 * (gestion) et delivery.manager (consultation) passent. Le périmètre est
 * toujours borné au tenant de l'acteur (company_id, fail-closed #3727).
 *
 * Les transitions de statut (livreur mobile) sont portées par
 * (événements de tracking) et DeliveryRoutePolicy — voir la matrice docs/architecture/DELIVERY_RBAC.md.
 */
final class DeliveryPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $this->canManage($actor);
    }

    public function view(Employee $actor, Delivery $delivery): bool
    {
        return $this->canManage($actor) && $delivery->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $this->canDispatch($actor);
    }

    public function update(Employee $actor, Delivery $delivery): bool
    {
        return $this->canDispatch($actor) && $delivery->company_id === $actor->company_id;
    }

    /**
     * Gestion des livraisons : dispatcher + admin (création, affectation).
     */
    public function canDispatch(Employee $actor): bool
    {
        return $actor->isManager()
            && in_array($actor->manager_role, ['principal', 'manager'], true);
    }

    /**
     * Consultation : dispatcher + admin + manager (rapports, lecture).
     */
    public function canManage(Employee $actor): bool
    {
        return $actor->isManager()
            && in_array($actor->manager_role, ['principal', 'manager', 'rh'], true);
    }

    /**
     * Événement de tracking (BC-26-D05) — le suivi temps réel est émis par
     * l'app mobile livreur (DELIVERY-203) et, en secours, par le dispatcher :
     *
     *  - dispatcher / admin : toute livraison du tenant ;
     *  - rider : uniquement les livraisons d'UNE de SES tournées
     *    (route.driver_id = id de l'employé) — jamais un collègue ;
     *  - manager : lecture seule (refusé ici).
     */
    public function store(Employee $actor, Delivery $delivery): bool
    {
        if ($delivery->company_id !== $actor->company_id) {
            return false;
        }

        if ($this->canDispatch($actor)) {
            return true;
        }

        if (! $actor->isEmployee() || $actor->status !== 'active') {
            return false;
        }

        // Rider : la livraison doit appartenir à une de SES tournées.
        return DeliveryStop::query()
            ->where('company_id', $actor->company_id)
            ->where('delivery_id', $delivery->id)
            ->whereHas('route', fn ($route) => $route->where('driver_id', $actor->id))
            ->exists();
    }

    /**
     * Ligne du temps interne : dispatcher/admin/manager + rider (sa tournée).
     */
    public function timeline(Employee $actor, Delivery $delivery): bool
    {
        if ($delivery->company_id !== $actor->company_id) {
            return false;
        }

        if ($this->canManage($actor)) {
            return true;
        }

        return $this->store($actor, $delivery);
    }
}
