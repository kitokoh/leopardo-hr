<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\Concerns;

use App\Core\Auth\Domain\Models\Employee;

/**
 * #7420 — Garde d'écriture des référentiels d'annonces (types, positions,
 * grille tarifaire).
 *
 * Les requêtes de création/mise à jour des référentiels d'annonces laissaient
 * `authorize()` à `true` avec le commentaire « rôles gestion tranchés au
 * controller » — mais NI `store()` ni `destroy()` ne faisaient ce contrôle :
 * tout employé authentifié du tenant pouvait créer ou supprimer une grille
 * tarifaire. Seul `update()` appliquait la règle. Ce trait centralise la règle
 * (alignée sur `TravelAdvertPolicy::moderate()` : principal/rh/manager) pour
 * les trois référentiels.
 */
trait AuthorizesAdvertCatalogWrite
{
    /** Rôles gestion autorisés à écrire dans les référentiels d'annonces. */
    private const ADVERT_CATALOG_WRITE_ROLES = ['principal', 'rh', 'manager'];

    private function authorizeAdvertCatalogWrite(Employee $actor): void
    {
        if (! $actor->hasManagerRole(...self::ADVERT_CATALOG_WRITE_ROLES)) {
            abort(403);
        }
    }
}
