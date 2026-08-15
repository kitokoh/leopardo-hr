<?php

declare(strict_types=1);

namespace App\Shared\Traits;

use App\Core\Tenant\Domain\Models\Company;
use App\Exceptions\MissingTenantContextException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Trait BelongsToCompany
 *
 * Automatically scopes Eloquent queries to the current tenant company and
 * auto-fills `company_id` on record creation.
 *
 * Usage:
 *   use App\Shared\Traits\BelongsToCompany;
 *
 * Required: the model must have a `company_id` column.
 *
 * ## Fail-closed (issue #3727)
 *
 * Sans compagnie courante (`current_company` non liée au conteneur), le scope
 * NE SAUTE PLUS en silence : en contexte HTTP il lève
 * `MissingTenantContextException` (403) pour éviter les requêtes
 * « toutes compagnies » ; en console (jobs/commandes/tests) il journalise un
 * warning structuré et conserve l'ancien comportement.
 *
 * Les accès cross-tenant légitimes (super-admin plateforme, jobs qui
 * implémentent `TenantScopedJob`, commandes de maintenance) doivent passer
 * explicitement par `->withoutGlobalScopes('company')` — jamais supposer le
 * skip silencieux.
 *
 * Comportement surchargeable : `config('tenant.fail_closed_scope')` — true
 * force le fail-closed partout, false le désactive (défaut : HTTP fail-closed,
 * console tolérante).
 */
trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder): void {
            $currentCompany = app()->bound('current_company') ? currentCompany() : null;

            if ($currentCompany instanceof Company) {
                $builder->where(
                    $builder->getModel()->qualifyColumn('company_id'),
                    $currentCompany->id
                );

                return;
            }

            if (self::tenantScopeShouldFailClosed()) {
                throw new MissingTenantContextException();
            }

            Log::warning('Tenant scope contourné sans compagnie courante (modèle {model})', [
                'model' => $builder->getModel()::class,
            ]);
        });

        static::creating(function (Model $model): void {
            $currentCompany = app()->bound('current_company') ? currentCompany() : null;

            if (! $currentCompany instanceof Company) {
                return;
            }

            if (empty($model->getAttribute('company_id'))) {
                $model->setAttribute('company_id', $currentCompany->id);
            }
        });
    }

    private static function tenantScopeShouldFailClosed(): bool
    {
        $configured = config('tenant.fail_closed_scope');

        if ($configured !== null) {
            return (bool) $configured;
        }

        // Défaut : contexte HTTP attend un tenant (middleware tenant) →
        // fail-closed. Console (jobs, commandes, tests) reste tolérante pour
        // ne pas casser les traitements sans tenant.
        return ! app()->runningInConsole();
    }
}
