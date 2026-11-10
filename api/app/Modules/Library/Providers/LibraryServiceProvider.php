<?php

declare(strict_types=1);

namespace App\Modules\Library\Providers;

use App\Modules\Library\Domain\Contracts\LibraryFileStorage;
use App\Modules\Library\Infrastructure\Services\LibraryPurchaseService;
use App\Modules\Library\Infrastructure\Storage\ObjectStorageLibraryFiles;
use Illuminate\Support\ServiceProvider;

/**
 * Provider du module Library (bibliothèque numérique publique).
 *
 * Socle domaine : migrations tenant (library_categories, library_authors,
 * library_collections, library_resources, library_reports), modèles DDD,
 * Policies (enregistrées au point unique `App\Providers\AuthServiceProvider`,
 * règle PA2-ARCH-008) et routes publiques/privées.
 *
 * Le module est volontairement autonome : il ne dépend d'aucun module métier
 * (RH, paiement, tenant) au premier périmètre. L'isolation tenant est assurée
 * par le trait `BelongsToCompany` (scope global) et la policy.
 *
 * Stockage des fichiers : le module ne dépend que du contrat
 * `LibraryFileStorage` ; l'adaptateur concret (S3-compatible ou disque local
 * privé) est choisi par configuration (`library.storage_disk`).
 */
class LibraryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LibraryFileStorage::class, function (): ObjectStorageLibraryFiles {
            return new ObjectStorageLibraryFiles(
                (string) config('library.storage_disk', 'library'),
            );
        });

        // #9238 — achat des ressources : le service ne depend que du contrat
        // PARTAGE d'encaissement (`Shared\Contracts\Payments`), jamais du
        // module Paiements (garde d'isolation #5584). L'implementation reelle
        // est liee par `PaiementsServiceProvider`.
        $this->app->singleton(LibraryPurchaseService::class);
    }

    public function boot(): void
    {
        // Les Policies métier sont enregistrées centralement dans
        // App\Providers\AuthServiceProvider (règle PA2-ARCH-008).
    }
}
