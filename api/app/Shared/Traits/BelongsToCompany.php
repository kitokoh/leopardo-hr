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
 * `MissingTenantContextException` (403, code `MISSING_TENANT_CONTEXT`) pour
 * éviter les requêtes « toutes compagnies » ; en console (jobs/commandes/tests)
 * il journalise un warning structuré et conserve l'ancien comportement.
 *
 * Deux voies légitimes pour une requête sans `current_company` :
 *   1. le caller contraint lui-même `company_id` (ex. routes publiques careers,
 *      relations `hasMany` dont la clé étrangère est `*.company_id`) ;
 *   2. opt-out explicite `->withoutGlobalScopes('company')` pour les accès
 *      cross-tenant volontaires (super-admin plateforme, jobs `TenantScopedJob`,
 *      lookups pré-tenant login/démo/trial) — jamais supposer le skip silencieux.
 *
 * Comportement surchargeable : `config('tenancy.fail_closed_without_context')`
 * (env `TENANT_FAIL_CLOSED_WITHOUT_CONTEXT`) — true force le fail-closed
 * partout, false le désactive (défaut : HTTP fail-closed, console tolérante).
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

            // Pas de contexte tenant : la requête DOIT être scopée explicitement
            // par le caller (contrainte company_id) ou opt-out via
            // withoutGlobalScopes('company'). Sinon échec explicite — jamais de
            // requête cross-tenant silencieuse (#3727).
            if (self::queryConstrainsCompany($builder)) {
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
        $configured = config('tenancy.fail_closed_without_context');

        if ($configured !== null) {
            return (bool) $configured;
        }

        // Défaut : contexte HTTP attend un tenant (middleware tenant) →
        // fail-closed. Console (jobs, commandes, tests) reste tolérante pour
        // ne pas casser les traitements sans tenant.
        return ! app()->runningInConsole();
    }

    /**
     * Détecte une contrainte `company_id` explicite dans la requête
     * (ou la clé étrangère d'une relation `hasMany`, ex. `employees.company_id`).
     */
    private static function queryConstrainsCompany(Builder $builder): bool
    {
        $wheres = $builder->getQuery()->wheres ?? [];

        foreach ($wheres as $where) {
            if (self::whereConstrainsCompany($where)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $where
     */
    private static function whereConstrainsCompany(array $where): bool
    {
        $column = $where['column'] ?? null;

        if (is_string($column) && str_ends_with($column, 'company_id')) {
            return true;
        }

        if (($where['type'] ?? '') === 'Nested') {
            $nested = $where['query'] ?? null;

            if ($nested instanceof \Illuminate\Database\Query\Builder) {
                foreach ($nested->wheres ?? [] as $nestedWhere) {
                    if (self::whereConstrainsCompany($nestedWhere)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
