<?php

declare(strict_types=1);

namespace Tests\Support\Fixtures;

use App\Contracts\Queue\TenantScopedJob;
use App\Core\Auth\Domain\Models\Employee;

/**
 * Fixture — « job CRM sans tenant » (issue #5736).
 *
 * Implémente TenantScopedJob en déclarant AUCUN tenant (`tenantCompanyId()`
 * retourne null) puis tente d'accéder à une donnée tenant (Employee).
 *
 * Le contrat #5736 impose qu'un tel job soit REJETÉ AVANT tout accès aux
 * données : la garde fail-closed du scope tenant (marqueur
 * `tenant_scope_required`, voir BelongsToCompany/#3727) doit lever
 * TenantContextMissingException dès la construction de la requête, sans
 * qu'aucune ligne ne soit lue ni écrite.
 */
final class TenantlessCrmProbeJob implements TenantScopedJob
{
    public function tenantCompanyId(): ?string
    {
        // Volontairement null : aucun contexte tenant à établir.
        return null;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [];
    }

    public function handle(): void
    {
        // Accès données tenant SANS contexte — doit être rejeté (fail-closed).
        Employee::query()->count();
    }
}
