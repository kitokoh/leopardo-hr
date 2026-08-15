<?php

declare(strict_types=1);

namespace App\Shared\Traits;

use App\Core\Tenant\Domain\Exceptions\TenantContextMissingException;
use App\Core\Tenant\Domain\Models\Company;
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
 * Issue #3727 (T004) : le scope était fail-open — sans `current_company`
 * liée, la requête s'exécutait sur TOUTES les compagnies (schéma partagé),
 * fuite cross-tenant silencieuse. Désormais, en contexte HTTP, une requête
 * sans contexte tenant ET sans contrainte `company_id` explicite échoue avec
 * `TenantContextMissingException`. Les requêtes volontairement scopées par le
 * caller (`where('company_id', ...)`, relations `hasMany`) restent autorisées.
 * En console (jobs/commands), le comportement historique est conservé
 * (contexte explicite via `TenantManager::withinTenant()`).
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
            // par le caller (ex. routes publiques careers qui filtrent
            // company_id, relations hasMany qui contraignent la clé étrangère).
            // Sinon échec explicite — jamais de requête cross-tenant silencieuse.
            if (! self::queryConstrainsCompany($builder)) {
                if (! app()->runningInConsole() && config('tenancy.fail_closed_without_context', true)) {
                    throw new TenantContextMissingException($builder->getModel()::class);
                }

                if (config('tenancy.log_missing_tenant_context', false)) {
                    Log::warning('tenant.scope.missing_context', [
                        'model' => $builder->getModel()::class,
                        'console' => app()->runningInConsole(),
                    ]);
                }
            }
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

    /**
     * Détecte une contrainte `company_id` explicite dans la requête
     * (où la clé étrangère d'une relation `hasMany`, ex. `employees.company_id`).
     *
     * @return bool true si au moins une clause filtre sur company_id
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
