<?php

declare(strict_types=1);

namespace App\Modules\Cameras\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Cameras\Domain\Models\CameraAlert;

/**
 * RBAC des alertes caméra (issue #7427).
 *
 * Calqué sur `FuelAlertPolicy` (#5813) : le manager lit les alertes et les
 * acquitte / clôture ; l'employé est refusé par défaut. Une alerte d'un autre
 * tenant est refusée (défense en profondeur : le scope global `company_id`
 * renvoie déjà 404).
 *
 * La même policy porte `viewAny` pour `CameraEvent` (lecture du journal
 * d'événements) : la méthode ne prend aucun modèle, seul le rôle arbitre.
 *
 * Enregistrement unique : `App\Providers\AuthServiceProvider` (garde
 * `check-policies-single-point.sh`).
 */
class CameraAlertPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function view(Employee $actor, CameraAlert $alert): bool
    {
        return $actor->isManager() && $alert->company_id === $actor->company_id;
    }

    public function acknowledge(Employee $actor, CameraAlert $alert): bool
    {
        return $this->view($actor, $alert);
    }

    public function resolve(Employee $actor, CameraAlert $alert): bool
    {
        return $this->view($actor, $alert);
    }
}
