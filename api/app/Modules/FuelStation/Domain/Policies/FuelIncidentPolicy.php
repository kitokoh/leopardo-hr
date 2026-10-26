<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Domain\Policies\Concerns\ChecksResourceScopedAccess;
use App\Modules\FuelStation\Domain\Models\FuelIncident;
use App\Modules\FuelStation\Domain\Models\FuelMaintenanceTask;

/**
 * RBAC des incidents FuelStation (FUEL-010, #5804). deny-by-default.
 *
 * - create : tout employé authentifié (pompiste) signale un incident ;
 * - view : manager, signalé par l'employé, ou assigné ;
 * - assign/resolve/close : manager uniquement (permissions par site via la
 *   FK composite (station_id, company_id) — cross-tenant impossible).
 *
 * Issue #7600 (R3) — croisement avec l'accès ressource-scopé par station.
 */
class FuelIncidentPolicy
{
    use ChecksResourceScopedAccess;

    /**
     * #7601 — la LISTE des incidents est un acte de supervision : elle expose
     * les signalements de tous les employés et de toutes les stations. Elle
     * était ouverte à tout employé authentifié (`return true`), ce qui laissait
     * un opérateur lire les incidents de la société entière. Réservée aux
     * managers (scopés par station le cas échéant). Le SIGNALEMENT reste ouvert
     * à tous via `create`, conformément à FUEL-010.
     */
    public function viewAny(Employee $actor): bool
    {
        return $this->canManageScopedResource($actor, 'fuel_station', null, $actor->isManager());
    }

    public function view(Employee $actor, FuelIncident $incident): bool
    {
        if ($incident->company_id !== (string) $actor->company_id) {
            return false;
        }

        if (! ($actor->isManager()
            || $incident->reported_by === $actor->id
            || $incident->assigned_to === $actor->id)) {
            return false;
        }

        return $this->canViewScopedResource($actor, 'fuel_station', $incident->station_id);
    }

    public function create(Employee $actor): bool
    {
        // #8578 (D6) — SIGNALER un incident reste un droit de terrain : un
        // pompiste qui voit une fuite doit pouvoir la declarer sans attendre un
        // manager. Deux tests l'exigent explicitement (creation + allowlist de
        // pieces jointes par un operateur). Le 403 « operator cannot manage »
        // porte sur la GESTION (lister, assigner, faire avancer), pas sur le
        // signalement. Restreindre `create` aux managers rendait le module
        // inutilisable en station -- on ne l'a pas fait.
        return $this->canOperateScopedResource($actor, 'fuel_station', null, true);
    }

    public function assign(Employee $actor, FuelIncident $incident): bool
    {
        return $actor->isManager()
            && $incident->company_id === (string) $actor->company_id
            && $this->canManageScopedResource($actor, 'fuel_station', $incident->station_id, true);
    }

    public function resolve(Employee $actor, FuelIncident $incident): bool
    {
        return $this->assign($actor, $incident);
    }

    public function close(Employee $actor, FuelIncident $incident): bool
    {
        return $this->assign($actor, $incident);
    }

    /**
     * Issue #7398 — dépôt d'une pièce jointe contrôlée sur un incident :
     * acte de gestion (mêmes règles que l'assignation).
     */
    public function attach(Employee $actor, FuelIncident $incident): bool
    {
        return $this->assign($actor, $incident);
    }

    /**
     * Issue #7398 — transition de workflow d'un incident : la cible est
     * validée par le service (`FuelIncident::TRANSITIONS`), les droits par
     * ici (dépôt/prise en charge/clôture = gestion).
     */
    public function transition(Employee $actor, FuelIncident $incident): bool
    {
        return $this->assign($actor, $incident);
    }

    public function report(Employee $actor): bool
    {
        return true;
    }

    public function createTask(Employee $actor): bool
    {
        return $this->canManageScopedResource($actor, 'fuel_station', null, $actor->isManager());
    }

    public function viewAnyTask(Employee $actor): bool
    {
        return $this->canManageScopedResource($actor, 'fuel_station', null, $actor->isManager());
    }

    public function viewTask(Employee $actor, FuelMaintenanceTask $task): bool
    {
        if (! ($actor->isManager() || $task->assigned_to === $actor->id)) {
            return false;
        }

        return $this->canViewScopedResource($actor, 'fuel_station', $task->station_id);
    }

    public function transitionTask(Employee $actor, FuelMaintenanceTask $task): bool
    {
        // #8578 (D6) — `transitionTask` deleguait a `viewTask`, qui autorise
        // AUSSI l'assigné non-manager (n'importe qui pouvait donc faire
        // avancer un cycle de vie). Le geste est une decision de GESTION :
        // manager du bon tenant.
        //
        // On n'exige PAS `canManageScopedResource` quand la tâche n'a pas de
        // station : ce cas retombe sur « principal uniquement », ce qui
        // refuserait à un manager légitime d'avancer une tâche preventive
        // company-wide (aucune ressource à scoper). Le manager du tenant est
        // l'autorité ; la restriction par ressource ne s'applique que s'il y a
        // une ressource à restreindre.
        if ($task->company_id !== (string) $actor->company_id) {
            return false;
        }

        if (! $actor->isManager()) {
            return false;
        }

        if ($task->station_id === null) {
            return true;
        }

        return $this->canManageScopedResource($actor, 'fuel_station', $task->station_id, true);
    }
}
