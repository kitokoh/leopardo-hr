<?php

declare(strict_types=1);

namespace App\Core\Tenant\Domain\Exceptions;

use RuntimeException;

/**
 * Issue #3727 (T004) : requête sur un modèle BelongsToCompany sans contexte
 * tenant explicite en HTTP.
 *
 * Le scope global « company » est fail-open quand aucune `current_company`
 * n'est liée au conteneur : la requête s'exécute alors sur TOUTES les
 * compagnies (schéma partagé) — fuite cross-tenant silencieuse. Depuis ce
 * correctif, une telle requête échoue explicitement (fail-closed) sauf si le
 * caller contraint déjà lui-même `company_id` (requête volontairement scopée,
 * ex. routes publiques careers) ou qu'il s'agit d'un contexte console/job
 * (comportement historique + warning).
 */
final class TenantContextMissingException extends RuntimeException
{
    public function __construct(string $modelClass)
    {
        parent::__construct(sprintf(
            'Query on %s without a tenant context: bind a current_company (TenantManager::setTenant/withinTenant) or constrain company_id explicitly.',
            $modelClass
        ));
    }
}
