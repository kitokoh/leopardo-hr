<?php

declare(strict_types=1);

namespace App\Modules\Cameras\Providers;

use App\Modules\Cameras\Infrastructure\Services\CameraService;
use App\Modules\Cameras\Infrastructure\Streaming\CameraStreamTokenService;
use Illuminate\Support\ServiceProvider;

/**
 * Module Cameras (BC-19 DEVICE).
 *
 * #7424 — ce provider était un **stub vide** (`register()` / `boot()` sans
 * aucun binding) alors que le module porte des services à cycle de vie : le
 * `CameraStreamTokenService` signe les jetons de flux (JWT HS256) et le
 * `CameraService` orchestre caméras, permissions, jetons tiers et journal
 * d'accès. Ils étaient résolus par auto-wiring, donc jamais explicitement
 * enregistrés : aucun point unique ne garantissait leur cycle de vie.
 *
 * Les deux services sont **sans état** (aucune valeur de configuration n'est
 * capturée au constructeur — `Config::get()` est appelé dans chaque méthode),
 * un `singleton` est donc sûr, y compris pour les tests qui modifient la
 * configuration après le boot.
 *
 * Convention alignée sur les modules voisins — `FuelStationServiceProvider`
 * enregistre également ses services en `singleton` dans `register()`.
 *
 * NOTE (constat, pas correction) — deux contrats declares dans
 * `Domain/Contracts` etaient, jusqu'au 2026-10-23, du **code mort** :
 *   - `AccessTokenServiceInterface` : ses signatures ne correspondaient PAS au
 *     `CameraStreamTokenService` reel (`issue(int $cameraId, int $employeeId,
 *     int $ttlSeconds): string` contre `issue(Camera $camera, int|string
 *     $actorEmployeeId): array`) — le contrat a ete ecrit avant le service et
 *     n'a jamais ete realigne ;
 *   - `CameraRepositoryInterface` : **aucune implementation** dans le depot,
 *     alors qu'il decrivait un modele `object`/`int $id` que les modeles reels
 *     (UUID, `Camera`) ne suivent pas.
 * Les lier en l'etat reviendrait a affirmer une architecture qui n'existe pas.
 *
 * ADR GOV-001 (categorie B) : les deux contrats ont ete **SUPPRIMES** le
 * 2026-10-23 plutot que realignes. Aucun des deux n'avait d'implementation,
 * de binding, ni de consommateur en production, en test, en route ou en
 * seeder ; les realigner aurait produit des contrats que personne n'appelle,
 * et `AccessTokenServiceInterface` decrivait un modele de jeton que le
 * service reel (JWT HS256 signe par `CameraStreamTokenService`) n'a jamais
 * utilise. Le jour ou l'abstraction sera reellement necessaire, elle devra
 * etre ecrite A PARTIR de `CameraStreamTokenService::issue()` — pas reprise
 * d'un contrat anterieur qui l'a contredit.
 */
class CamerasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CameraStreamTokenService::class);
        $this->app->singleton(CameraService::class);
    }

    public function boot(): void
    {
        // Rien à démarrer : le module n'enregistre ni route ni listener ici
        // (les routes vivent dans api/routes/modules/cameras.php).
    }
}
