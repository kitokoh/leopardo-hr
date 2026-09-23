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
 * Issue #7960 — bypass explicite du scope tenant : ne JAMAIS appeler
 * `withoutGlobalScope('company')` directement dans le code applicatif.
 * Utiliser les wrappers nommés qui documentent l'intention :
 *   - `Model::forCompany($company)` — lecture ciblée sur UN tenant explicite
 *     (webhooks, surfaces publiques résolvant le tenant par slug/token…).
 *   - `Model::crossTenantForPlatformAdmin()` — lectures plateforme
 *     (super-admin, health checks multi-tenants).
 *   - `Model::crossTenantForSystemTask('raison #issue')` — console, jobs,
 *     schedulers parcourant tous les tenants ; la justification est
 *     obligatoire et versionnée au point d'appel.
 * La garde CI `dev-hub/tools/check-without-global-scope.sh` (ratchet sur
 * baseline) fait échouer tout nouvel usage brut de `withoutGlobalScope`.
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

        static::updating(function (Model $model): void {
            // Issue #7646 (suite) — le chemin `updating` était resté ouvert :
            // `company_id` mass-assignable permettait de DÉPLACER un
            // enregistrement existant vers un autre tenant via un payload
            // d'update validé. Même contrat que `creating` : sous tenant
            // actif, `company_id` ne change jamais — toute tentative est
            // écrasée et journalisée. Hors contexte tenant (console, jobs
            // de maintenance), le comportement permissif est conservé.
            if (! $model->isDirty('company_id')) {
                return;
            }

            $currentCompany = app()->bound('current_company') ? currentCompany() : null;

            if (! $currentCompany instanceof Company) {
                return;
            }

            $original = $model->getRawOriginal('company_id');

            Log::warning('BelongsToCompany: tentative de modification de company_id sous tenant actif — valeur restaurée (spoof cross-tenant sur update, #7646)', [
                'model' => $model::class,
                'original_company_id' => (string) $original,
                'attempted_company_id' => (string) $model->getAttribute('company_id'),
                'tenant_company_id' => $currentCompany->id,
            ]);

            $model->setAttribute('company_id', $original);
        });
    }

    /**
     * #7960 — Requête volontairement portée sur UN tenant explicite, hors
     * contexte `current_company` (webhook, device, boutique publique…).
     * Remplace le couple `withoutGlobalScope('company')->where('company_id', …)`.
     *
     * @param  Builder<static>  $builder
     * @return Builder<static>
     */
    public function scopeForCompany(Builder $builder, Company|string|int $company): Builder
    {
        $companyId = $company instanceof Company ? $company->id : $company;

        return $builder
            ->withoutGlobalScope('company')
            ->where($builder->getModel()->qualifyColumn('company_id'), $companyId);
    }

    /**
     * #7960 — Lecture cross-tenant assumée pour les surfaces plateforme
     * (super-admin, santé des tenants). N'utiliser que derrière une
     * autorisation plateforme déjà vérifiée.
     *
     * @param  Builder<static>  $builder
     * @return Builder<static>
     */
    public function scopeCrossTenantForPlatformAdmin(Builder $builder): Builder
    {
        return $builder->withoutGlobalScope('company');
    }

    /**
     * #7960 — Parcours cross-tenant d'une tâche système (console, job,
     * scheduler). La justification est obligatoire : elle documente le
     * point d'appel et le rend auditable (`rg "crossTenantForSystemTask"`).
     *
     * @param  Builder<static>  $builder
     * @param  string  $justification  Raison + n° d'issue — ne doit pas être
     *   vide (vérifié à l'exécution par l'assert ci-dessous : la sonde reste
     *   non tautologique, doctrine #8014 — pas de `non-empty-string` ici).
     * @return Builder<static>
     */
    public function scopeCrossTenantForSystemTask(Builder $builder, string $justification): Builder
    {
        assert($justification !== '', 'crossTenantForSystemTask: justification obligatoire (#7960)');

        return $builder->withoutGlobalScope('company');
    }
}
