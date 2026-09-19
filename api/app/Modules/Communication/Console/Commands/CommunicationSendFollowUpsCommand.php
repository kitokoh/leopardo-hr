<?php

declare(strict_types=1);

namespace App\Modules\Communication\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Communication\Domain\Support\CommunicationFeatures;
use App\Modules\Communication\Infrastructure\Services\CommunicationFollowUpScheduler;
use Illuminate\Console\Command;

/**
 * Relances automatiques schedulees (BC-29 COMMUNICATION, R4 #7689 — spec
 * §3.4, pattern `crm:tasks:send-overdue-reminders` #5720).
 *
 * IDEMPOTENTE par construction : la passe MATERIALISE les echeances dans la
 * table de deduplication `communication_follow_ups` (UNIQUE company×thread×
 * rule×step) puis dispatche un job par echeance due (queue `communication`)
 * — rejouer la commande ne cree ni doublon d'echeance ni double envoi
 * (« relance part une seule fois par echeance »). Les garde-fous (reponse
 * detectee, opt-out, consentement CRM, quiet hours, plafonds) sont evalues
 * dans le job, juste avant l'envoi.
 *
 * Seuls les tenants actifs AVEC le module `communication` actif (kill
 * switch) sont traites.
 */
class CommunicationSendFollowUpsCommand extends Command
{
    protected $signature = 'communication:send-follow-ups
        {--company= : Cibler un tenant precis}';

    protected $description = 'Materialise et envoie les relances automatiques dues (Communication R4, #7689).';

    public function handle(TenantManager $tenantManager, CommunicationFollowUpScheduler $scheduler): int
    {
        $companies = Company::query()
            ->where('status', 'active')
            ->when($this->option('company'), fn ($query, $id) => $query->whereKey($id))
            ->orderBy('id')
            ->get()
            ->filter(fn (Company $company): bool => $company->hasFeature(CommunicationFeatures::COMMUNICATION));

        $dispatched = 0;

        foreach ($companies as $company) {
            // Contexte tenant explicite (search_path) — pattern
            // communication:sync-mailboxes (R2 #7687).
            $dispatched += (int) $tenantManager->withinTenant(
                $company,
                fn (): int => $scheduler->run((string) $company->id),
            );
        }

        $this->info("Relances planifiees : {$dispatched}.");

        return self::SUCCESS;
    }
}
