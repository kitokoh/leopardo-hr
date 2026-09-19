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

                // Hors contexte tenant (console, jobs, seeders, fixtures de
                // test) : comportement permissif inchangé — une valeur
                // `company_id` fournie explicitement est conservée (#7646).
                return;
            }

            // Issue #7646 — écriture cross-tenant : quand un tenant est actif,
            // `company_id` est TOUJOURS forcé depuis le tenant courant.
            // `company_id` figure dans le $fillable de la plupart des modèles
            // du dépôt : tout endpoint laissant passer `company_id` dans un
            // payload permettait d'écrire dans un autre tenant. Une écriture
            // inter-tenant légitime passe par TenantManager::withinTenant()
            // du tenant CIBLE — jamais par un `company_id` mass-assigné.
            $provided = $model->getAttribute('company_id');

            if (is_scalar($provided)
                && (string) $provided !== ''
                && (string) $provided !== $currentCompany->id) {
                // Valeur différente fournie = tentative de spoof (ou bug
                // appelant) : on écrase et on journalise (#7646).
                Log::warning('BelongsToCompany: company_id fourni différent du tenant actif — valeur écrasée (tentative de spoof cross-tenant, #7646)', [
                    'model' => $model::class,
                    'provided_company_id' => (string) $provided,
                    'tenant_company_id' => $currentCompany->id,
                ]);
            }

            $model->setAttribute('company_id', $currentCompany->id);
        });
    }
}
