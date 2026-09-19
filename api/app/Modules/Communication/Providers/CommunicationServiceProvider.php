<?php

declare(strict_types=1);

namespace App\Modules\Communication\Providers;

use App\AI\Support\AIToolDefinitionRegistry;
use App\Modules\Communication\Console\Commands\CommunicationSyncMailboxesCommand;
use App\Modules\Communication\Domain\Support\CommunicationAiToolCatalog;
use Illuminate\Support\ServiceProvider;

/**
 * Provider du module Communication (BC-29 COMMUNICATION, R0 #7685).
 *
 * Boîte mail connectée + IA (spec validée
 * docs/specifications/MODULE_COMMUNICATION_EMAIL_IA.md, PR #7645) :
 * R0 = enregistrement du module (feature flag tenant `communication`,
 * middleware `module.communication`, routes squelette). R2 (#7687) ajoute
 * la commande de polling Gmail `communication:sync-mailboxes` (schedulee
 * dans routes/console.php). R3 (#7688) declare le tool IA `email_classify`
 * au contrat A3 (AIToolDefinitionRegistry, garde d'idempotence #6947) —
 * la liaison CRM passe par le contrat partage
 * `App\Shared\Contracts\Crm\EmailContactDirectory` binde par le module
 * CRM (isolation #5584). Les services OAuth/sync/classification sont
 * resolus par le conteneur sans binding explicite (constructeurs concrets).
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

        // R3 (#7688) — outils IA du module (contrat A3 #6850, garde
        // d'idempotence #6947 : le collecteur statique survit aux boots).
        foreach (CommunicationAiToolCatalog::definitions() as $definition) {
            if (! AIToolDefinitionRegistry::has($definition->name)) {
                AIToolDefinitionRegistry::register($definition);
            }
        }
    }
}
