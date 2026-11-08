<?php

declare(strict_types=1);

namespace App\Modules\Library\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Library\Domain\Models\LibraryResource;

/**
 * RBAC des ressources de la bibliothèque.
 *
 * Gestion (CRUD, publication, archivage) réservée au responsable du tenant
 * (sous-rôles `principal`/`rh`) ; lecture ouverte aux membres du tenant
 * (scope `company_id` vérifié). deny-by-default : aucun rôle = refus.
 *
 * Les ressources du catalogue public Leopardo (`company_id` NULL) ne sont
 * modifiables que par la surface superadmin (hors de cette policy tenant).
 */
class LibraryResourcePolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, LibraryResource $resource): bool
    {
        return $resource->company_id === (string) $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }

    public function update(Employee $actor, LibraryResource $resource): bool
    {
        return $actor->hasManagerRole('principal', 'rh')
            && $resource->company_id === (string) $actor->company_id;
    }

    public function delete(Employee $actor, LibraryResource $resource): bool
    {
        return $this->update($actor, $resource);
    }

    /**
     * Publication / dépublication / archivage — même portée que l'édition.
     */
    public function publish(Employee $actor, LibraryResource $resource): bool
    {
        return $this->update($actor, $resource);
    }
}
