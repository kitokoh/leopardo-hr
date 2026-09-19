<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Tenant\Domain\Models\Company;

/**
 * Issue #7646 — depuis le durcissement de `BelongsToCompany`, `company_id`
 * est FORCÉ depuis le tenant actif à la création (anti-spoof cross-tenant).
 * Une fixture appartenant à un AUTRE tenant doit donc être créée sous le
 * contexte de SON tenant. Ce helper bascule le tenant courant le temps d'un
 * callback puis restaure le contexte précédent (miroir en test du pattern
 * TenantManager::withinTenant, sans bascule de search_path).
 */
trait SwitchesTenantContext
{
    /**
     * @template TReturn
     *
     * @param  \Closure(): TReturn  $callback
     * @return TReturn
     */
    protected function withTenantContext(Company $company, \Closure $callback): mixed
    {
        $previous = app()->bound('current_company') ? app('current_company') : null;
        app()->instance('current_company', $company);

        try {
            return $callback();
        } finally {
            if ($previous instanceof Company) {
                app()->instance('current_company', $previous);
            } else {
                app()->forgetInstance('current_company');
            }
        }
    }
}
