<?php

declare(strict_types=1);

namespace App\Core\Tenant\Infrastructure\Services;

use App\Core\Tenant\Domain\Exceptions\TenantContextMissingException;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use Illuminate\Support\Facades\Log;

/**
 * TenantContextGuard — garde fail-closed générique du contexte tenant.
 *
 * Issue #5706 (CRM-V0-02) — verrouillage du contexte tenant pour les
 * surfaces hors HTTP (events, jobs, services) : toute opération déclarée
 * tenant-scopée qui s'exécute sans compagnie courante échoue immédiatement
 * plutôt que de s'exécuter sans scope (fuite cross-tenant potentielle).
 *
 * La surface HTTP est déjà protégée par TenantMiddleware + BelongsToCompany
 * (marqueur `tenant_scope_required`, exception 403 TENANT_CONTEXT_MISSING,
 * issue #3727) ; cette garde étend le même principe fail-closed aux appels
 * de service programmatiques.
 */
final class TenantContextGuard
{
    public function __construct(private readonly TenantManager $tenantManager) {}

    /**
     * Retourne la compagnie courante si un contexte tenant est actif, sinon
     * échoue immédiatement (fail-closed).
     *
     * @param  string  $operation  Nom de l'opération (logging structuré).
     * @return Company
     *
     * @throws TenantContextMissingException quand aucun contexte tenant n'est actif.
     */
    public function assertHasTenant(string $operation): Company
    {
        $company = $this->tenantManager->current();

        if (! $company instanceof Company) {
            Log::channel('structured')->warning('tenant.context.missing', [
                'operation' => $operation,
            ]);

            throw new TenantContextMissingException;
        }

        return $company;
    }
}
