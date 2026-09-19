<?php

declare(strict_types=1);

namespace App\Modules\Communication\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Provider du module Communication (BC-29 COMMUNICATION, R0 #7685).
 *
 * Boîte mail connectée + IA (spec validée
 * docs/specifications/MODULE_COMMUNICATION_EMAIL_IA.md, PR #7645) :
 * R0 = enregistrement du module (feature flag tenant `communication`,
 * middleware `module.communication`, routes squelette). Les services
 * OAuth Google, sync Gmail, classification IA et relances arrivent avec
 * les lots R1→R5 — le provider reste volontairement minimal tant qu'il
 * n'a pas de service à binder (pattern CatalogServiceProvider #6880).
 */
class CommunicationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Aucun binding en R0.
    }

    public function boot(): void
    {
        // Les Policies métier seront enregistrées centralement dans
        // App\Providers\AuthServiceProvider (règle PA2-ARCH-008) à partir
        // des lots R1+ (aucun modèle en R0).
    }
}
