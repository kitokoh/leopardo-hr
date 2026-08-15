<?php

declare(strict_types=1);

namespace App\Shared\Traits;

use App\Core\Tenant\Domain\Exceptions\TenantContextMissingException;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
 */
trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder): void {
            $currentCompany = app()->bound('current_company') ? currentCompany() : null;

            if (! $currentCompany instanceof Company) {
                // Issue #3727 — fail-closed : sur la surface API tenant
                // (marqueur posé par TenantMiddleware), une requête sans
                // compagnie courante est une fuite cross-tenant potentielle
                // → 403. Hors surface tenant (console, jobs, routes publiques,
                // super-admin plateforme), le comportement non scopé reste
                // permis et doit être explicité via withoutGlobalScopes().
                if (app()->bound('tenant_scope_required')) {
                    throw new TenantContextMissingException;
                }

                return;
            }

            $builder->where(
                $builder->getModel()->qualifyColumn('company_id'),
                $currentCompany->id
            );
        });

        static::creating(function (Model $model): void {
            $currentCompany = app()->bound('current_company') ? currentCompany() : null;

            if (! $currentCompany instanceof Company) {
                // Issue #3727 — ne jamais créer de donnée tenant orpheline sur
                // la surface API tenant (même garde fail-closed que le scope).
                if (app()->bound('tenant_scope_required')) {
                    throw new TenantContextMissingException;
                }

                return;
            }

            if (empty($model->getAttribute('company_id'))) {
                $model->setAttribute('company_id', $currentCompany->id);
            }
        });
    }
}

