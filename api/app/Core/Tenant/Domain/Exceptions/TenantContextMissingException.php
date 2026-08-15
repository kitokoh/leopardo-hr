<?php

declare(strict_types=1);

namespace App\Core\Tenant\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * Issue #3727 — fail-closed du scope tenant.
 *
 * Une requête passée par TenantMiddleware (surface API tenant) qui poursuit
 * sans `current_company` lié (employé `ordinary` sans compagnie) ne doit
 * JAMAIS interroger un modèle BelongsToCompany : sans garde, le scope global
 * sautait silencieusement et la requête couvrait TOUTES les compagnies
 * (fuite cross-tenant). Cette exception convertit le fail-open en 403.
 *
 * Hors surface tenant (console, jobs, seeders, routes publiques, super-admin
 * plateforme), l'absence de binding reste permise : ces contextes utilisent
 * `withoutGlobalScopes()` explicitement quand ils doivent traverser les tenants.
 */
class TenantContextMissingException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'Tenant context is required for this query.',
            403,
            'TENANT_CONTEXT_MISSING'
        );
    }

    public function statusCode(): int
    {
        return 403;
    }
}
