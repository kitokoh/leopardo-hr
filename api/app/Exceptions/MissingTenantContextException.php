<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Levée par le scope global `BelongsToCompany` quand une requête HTTP touche
 * un modèle tenant sans contexte compagnie (`current_company`) — fail-closed
 * (issue #3727) : sans compagnie courante, le scope saute désormais au lieu
 * de laisser la requête filtre toutes les compagnies.
 *
 * Les accès cross-tenant légitimes (super-admin plateforme, jobs/commandes)
 * doivent passer explicitement par `->withoutGlobalScopes('company')`.
 */
class MissingTenantContextException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'Contexte compagnie requis pour cette requête (tenant non résolu).',
            403,
            'MISSING_TENANT_CONTEXT'
        );
    }
}
