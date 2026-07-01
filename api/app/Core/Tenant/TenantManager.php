<?php

declare(strict_types=1);

namespace App\Core\Tenant;

use App\Models\Company;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * TenantManager — Gestionnaire du contexte multi-tenant.
 *
 * Responsabilités :
 *   - Activer/désactiver le contexte d'une company (tenant)
 *   - Manipuler le `search_path` PostgreSQL pour l'isolation des données
 *   - Exposer `withinTenant()` pour les jobs/commands à contexte ponctuel
 *
 * Enregistrement :
 *   AppServiceProvider::register() → $this->app->singleton(TenantManager::class)
 *   Le singleton App\Services\TenantManager est conservé comme alias
 *   de backward compat (voir App\Services\TenantManager).
 *
 * Utilisation normale :
 *   $manager = app(\App\Core\Tenant\TenantManager::class);
 *   $manager->setTenant($company);
 *
 * Utilisation dans les jobs / commandes :
 *   $manager->withinTenant($company, fn() => ... traitement ...);
 *
 * Accès global en lecture (helpers.php) :
 *   currentCompany() → app('current_company')
 */
final class TenantManager
{
    private string $previousPath = 'public';

    private ?Company $previousCompany = null;

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Bascule la connexion sur le schéma du tenant donné.
     *
     * Enregistre la company dans le conteneur Laravel sous la clé
     * `current_company`, et met à jour le `search_path` PostgreSQL si
     * la connexion active est PostgreSQL.
     */
    public function setTenant(Company $company): void
    {
        $this->previousCompany = app()->bound('current_company')
            ? app('current_company')
            : null;

        if ($this->isPostgres()) {
            $this->previousPath = $this->currentSearchPath();
            DB::statement('SET search_path TO '.$company->getSafeSearchPath());
        }

        app()->instance('current_company', $company);
    }

    /**
     * Restaure le contexte tenant précédent.
     *
     * Appeler après `setTenant()` quand le traitement est terminé en dehors
     * d'un scope `withinTenant()`.
     */
    public function resetToPrevious(): void
    {
        if ($this->isPostgres()) {
            DB::statement('SET search_path TO '.$this->previousPath);
        }

        $this->restoreCompanyContext($this->previousCompany);
        $this->previousCompany = null;
    }

    /**
     * Exécute une closure dans le contexte d'un tenant, puis restaure.
     *
     * @template T
     * @param  Closure(): T  $cb
     * @return T
     */
    public function withinTenant(Company $company, Closure $cb): mixed
    {
        $oldPath    = 'public';
        $oldCompany = app()->bound('current_company') ? app('current_company') : null;

        if ($this->isPostgres()) {
            $oldPath = $this->currentSearchPath();
        }

        $this->setTenant($company);

        try {
            return $cb();
        } finally {
            if ($this->isPostgres()) {
                DB::statement('SET search_path TO '.$oldPath);
            }

            $this->restoreCompanyContext($oldCompany);
            $this->previousCompany = null;
        }
    }

    /**
     * Retourne la company actuellement active, ou null si aucun contexte tenant.
     */
    public function current(): ?Company
    {
        return app()->bound('current_company') ? app('current_company') : null;
    }

    /**
     * Retourne true si un contexte tenant est actif.
     */
    public function hasTenant(): bool
    {
        return app()->bound('current_company') && (app('current_company') instanceof Company);
    }

    /**
     * Désactive le contexte tenant (sans restaurer).
     * Utile dans les artisan commands / tests.
     */
    public function clearTenant(): void
    {
        if ($this->isPostgres()) {
            DB::statement('SET search_path TO public');
        }

        app()->forgetInstance('current_company');
    }

    // ── Helpers internes ──────────────────────────────────────────────────────

    private function restoreCompanyContext(?Company $company): void
    {
        if ($company instanceof Company) {
            app()->instance('current_company', $company);
        } else {
            app()->forgetInstance('current_company');
        }
    }

    private function isPostgres(): bool
    {
        return DB::getDriverName() === 'pgsql';
    }

    private function currentSearchPath(): string
    {
        /** @var object{search_path: string}|null $row */
        $row = DB::selectOne('SHOW search_path');

        return $row->search_path ?? 'public';
    }
}
