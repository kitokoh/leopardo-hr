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
 * NOTE (constat, pas correction) — deux contrats déclarés dans
 * `Domain/Contracts` sont, à ce jour, du **code mort** :
 *   - `AccessTokenServiceInterface` : ses signatures ne correspondent PAS au
 *     `CameraStreamTokenService` réel (`issue(int $cameraId, int $employeeId,
 *     int $ttlSeconds): string` contre `issue(Camera $camera, int|string
 *     $actorEmployeeId): array`) — le contrat a été écrit avant le service et
 *     n'a jamais été réaligné ;
 *   - `CameraRepositoryInterface` : **aucune implémentation** dans le dépôt,
 *     alors qu'il décrit un modèle `object`/`int $id` que les modèles réels
 *     (UUID, `Camera`) ne suivent pas.
 * Les lier en l'état reviendrait à affirmer une architecture qui n'existe pas.
 * Ils ne sont donc **pas** liés ici : leur réalignement (ou leur retrait) est
 * un travail à part, qui touche la baseline PHPStan Strict.
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
