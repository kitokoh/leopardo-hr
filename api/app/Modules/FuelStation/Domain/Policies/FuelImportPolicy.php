<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Domain\Policies\Concerns\ChecksResourceScopedAccess;
use App\Modules\FuelStation\Domain\Models\FuelImport;

/**
 * RBAC de l'import CSV (FUEL-018, #5812) : opération manager,
 * croisée avec l'accès ressource-scopé par station (#7600).
 */
class FuelImportPolicy
{
    use ChecksResourceScopedAccess;

    public function viewAny(Employee $actor): bool
    {
        return $this->canManageScopedResource($actor, 'fuel_station', null, $actor->isManager());
    }

    public function view(Employee $actor, FuelImport $import): bool
    {
        return $actor->isManager()
            && $this->canViewScopedResource($actor, 'fuel_station', null, true);
    }

    public function create(Employee $actor): bool
    {
        return $this->canManageScopedResource($actor, 'fuel_station', null, $actor->isManager());
    }

    /**
     * #7601 — `FuelImportController::commit()` et `cancel()` autorisent
     * l'abilité `update`, mais la policy n'exposait que `viewAny`/`view`/`create`.
     * `Gate::authorize` échouait donc systématiquement en 403 : aucun import
     * n'était validable ni annulable, par personne, y compris le manager.
     * L'acte est de même nature que `create` (écriture manager scopée).
     */
    public function update(Employee $actor, ?FuelImport $import = null): bool
    {
        // Les imports sont scopes a la societe, pas a une station : le modele
        // FuelImport n'expose pas de colonne station_id. On passe donc null,
        // comme create(), pour ne pas inventer une portee inexistante.
        return $this->canManageScopedResource($actor, 'fuel_station', null, $actor->isManager());
    }
}
