<?php

declare(strict_types=1);

namespace App\Modules\Communication\Providers;

use App\Modules\Communication\Console\Commands\CommunicationSyncMailboxesCommand;
use Illuminate\Support\ServiceProvider;

/**
 * Provider du module Communication (BC-29 COMMUNICATION, R0 #7685).
 *
 * Boîte mail connectée + IA (spec validée
 * docs/specifications/MODULE_COMMUNICATION_EMAIL_IA.md, PR #7645) :
 * R0 = enregistrement du module (feature flag tenant `communication`,
 * middleware `module.communication`, routes squelette). R2 (#7687) ajoute
 * la commande de polling Gmail `communication:sync-mailboxes` (schedulee
 * dans routes/console.php). Les services OAuth/sync sont resolus par le
 * conteneur sans binding explicite (constructeurs concrets).
 */
class CommunicationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Aucun binding necessaire (resolution auto par le conteneur).
    }

    public function boot(): void
    {
        // Les Policies metier sont enregistrees centralement dans
        // App\Providers\AuthServiceProvider (regle PA2-ARCH-008).

        // Commandes artisan du module (hors app/Console/Commands ->
        // enregistrement explicite, pattern TravelAgencyServiceProvider).
        $this->commands([
            CommunicationSyncMailboxesCommand::class,
        ]);
    }
}
