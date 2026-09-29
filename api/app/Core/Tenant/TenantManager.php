<?php

declare(strict_types=1);

namespace App\Core\Tenant;

use App\Core\Tenant\Domain\Models\Company;
use Closure;
use Illuminate\Database\QueryException;
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
 *   Les anciens shims App\Services\* ont été supprimés (issue #1494).
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
            try {
                DB::statement('SET search_path TO '.$this->previousPath);
            } catch (QueryException $exception) {
                // PostgreSQL rejects every statement after a failed query in
                // the current transaction. Do not let this cleanup 25P02
                // replace the original provisioning exception; the outer
                // transaction/test teardown will rollback the failed scope.
                if ($exception->getCode() !== '25P02') {
                    throw $exception;
                }
            }
        }

        $this->restoreCompanyContext($this->previousCompany);
        $this->previousCompany = null;
    }

    /**
     * Exécute une closure dans le contexte d'un tenant, puis restaure.
     *
     * @template T
     *
     * @param  Closure(): T  $cb
     * @return T
     */
    public function withinTenant(Company $company, Closure $cb): mixed
    {
        $oldPath = 'public';
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
     * Exécute une closure sous un `search_path` PostgreSQL explicite, puis
     * restaure systématiquement le précédent (try/finally) — BOS-019 (#8204).
     *
     * C'est l'API UNIQUE de bascule « brute » de search_path, pour les
     * traitements qui ne changent PAS le contexte tenant (`current_company`) :
     * surfaces publiques (kiosk), vues portefeuille plateforme, sondes. Tout
     * `SET search_path` manuel hors de cette classe est un motif de refus en
     * revue (filet de sécurité : `EnsureKioskSearchPathReset`, #3368).
     *
     * Pour une bascule de tenant complète (company + search_path), utiliser
     * `withinTenant()`.
     *
     * @template T
     *
     * @param  Closure(): T  $cb
     * @return T
     */
    public function withinSearchPath(string $searchPath, Closure $cb): mixed
    {
        if (! $this->isPostgres()) {
            return $cb();
        }

        $previous = $this->currentSearchPath();
        DB::statement('SET search_path TO '.$this->assertSafeSearchPath($searchPath));

        try {
            return $cb();
        } finally {
            try {
                DB::statement('SET search_path TO '.$previous);
            } catch (QueryException $exception) {
                // Même garde que resetToPrevious() : ne pas laisser un 25P02 de
                // nettoyage masquer l'exception d'origine — la transaction est
                // déjà morte et sera rollbackée par le scope amont.
                if ($exception->getCode() !== '25P02') {
                    throw $exception;
                }
            }
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

    /**
     * Validation fail-closed d'une chaîne search_path : identifiants
     * PostgreSQL simples, guillemets et virgules uniquement — jamais
     * d'interpolation libre dans un `SET search_path` (BOS-019).
     */
    private function assertSafeSearchPath(string $searchPath): string
    {
        if (preg_match('/^[a-zA-Z0-9_",\s]+$/', $searchPath) !== 1) {
            throw new \InvalidArgumentException('search_path invalide : caractères non autorisés');
        }

        return $searchPath;
    }

    private function currentSearchPath(): string
    {
        /** @var object{search_path: string}|null $row */
        $row = DB::selectOne('SHOW search_path');

        return $row->search_path ?? 'public';
    }
}
