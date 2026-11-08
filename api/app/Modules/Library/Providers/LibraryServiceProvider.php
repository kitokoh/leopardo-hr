<?php

declare(strict_types=1);

namespace App\Modules\Library\Providers;

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
 */
class LibraryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Aucun service à binder au premier périmètre.
    }

    public function boot(): void
    {
        // Les Policies métier sont enregistrées centralement dans
        // App\Providers\AuthServiceProvider (règle PA2-ARCH-008).
    }
}
