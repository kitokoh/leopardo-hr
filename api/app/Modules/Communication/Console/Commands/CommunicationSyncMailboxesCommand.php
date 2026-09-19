<?php

declare(strict_types=1);

namespace App\Modules\Communication\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Communication\Domain\Models\CommunicationIntegration;
use App\Modules\Communication\Domain\Support\CommunicationFeatures;
use App\Modules\Communication\Infrastructure\Jobs\SyncGmailMailboxJob;
use Illuminate\Console\Command;

/**
 * Polling schedule des boites Gmail connectees (BC-29, R2 #7687) —
 * planifiee toutes les 5 minutes dans `routes/console.php` (spec §3.2 :
 * V1 = polling 5 min, push Pub/Sub en V1.1).
 *
 * IDEMPOTENTE par construction : elle ne fait que DISPATCHER un job par
 * boite `active` (queue `communication`) ; la passe de sync upserte sur
 * les cles Gmail (resync sans doublons) et `WithoutOverlapping` protege
 * chaque boite contre les passes concurrentes — rejouer la commande est
 * toujours sur.
 *
 * Throttling : seules les boites des tenants actifs AVEC le module
 * `communication` actif sont traitees (kill switch conserve) ; les boites
 * `revoked`/`error` sont ignorees (une boite en erreur ne re-synce que
 * quand l'utilisateur se reconnecte). Le dispatch est etale (`delay`
 * progressif) pour lisser la charge sur l'API Gmail.
 */
class CommunicationSyncMailboxesCommand extends Command
{
    /**
     * Etalement du dispatch : N secondes entre deux boites — lisse les
     * quotas Gmail quand beaucoup de boites sont connectees.
     */
    private const STAGGER_SECONDS = 2;

    protected $signature = 'communication:sync-mailboxes
        {--company= : Cibler un tenant precis}';

    protected $description = 'Planifie la sync Gmail incrementale des boites connectees actives (Communication R2, #7687).';

    public function handle(TenantManager $tenantManager): int
    {
        $companies = Company::query()
            ->where('status', 'active')
            ->when($this->option('company'), fn ($query, $id) => $query->whereKey($id))
            ->orderBy('id')
            ->get()
            ->filter(fn (Company $company): bool => $company->hasFeature(CommunicationFeatures::COMMUNICATION));

        $dispatched = 0;

        foreach ($companies as $company) {
            // Contexte tenant explicite (search_path) : pattern
            // travel:expire-pending-bookings — sur en mode schema isole.
            $integrations = $tenantManager->withinTenant(
                $company,
                fn () => CommunicationIntegration::query()
                    ->where('company_id', (string) $company->id)
                    ->where('provider', CommunicationIntegration::PROVIDER_GOOGLE)
                    ->where('status', CommunicationIntegration::STATUS_ACTIVE)
                    ->orderBy('id')
                    ->pluck('id'),
            );

            foreach ($integrations as $integrationId) {
                SyncGmailMailboxJob::dispatch((string) $company->id, (string) $integrationId)
                    ->delay(now()->addSeconds($dispatched * self::STAGGER_SECONDS));

                $dispatched++;
            }
        }

        $this->info("Syncs Gmail planifiees : {$dispatched}.");

        return self::SUCCESS;
    }
}
