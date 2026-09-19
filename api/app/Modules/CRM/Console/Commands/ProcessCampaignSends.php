<?php

declare(strict_types=1);

namespace App\Modules\CRM\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\CRM\Application\Services\CampaignService;
use App\Modules\CRM\Domain\Enums\CampaignStatus;
use App\Modules\CRM\Domain\Models\CrmCampaign;
use App\Modules\CRM\Infrastructure\Jobs\ProcessCampaignSendsJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Worker planifié des campagnes email — Issue #7751 (toutes les 5 min).
 *
 * Trois responsabilités :
 *   1. AUTO-START : les campagnes `scheduled` dont `scheduled_at` est passé
 *      démarrent toutes seules (résolution d'audience + filtre consentement
 *      fail-closed via `CampaignService::start()`, sous contexte tenant) —
 *      un start invalide (contenu manquant, audience vide…) est journalisé
 *      et n'interrompt pas les autres campagnes ;
 *   2. DRAINAGE : chaque campagne email `running` reçoit un
 *      `ProcessCampaignSendsJob` (queue) qui traite ses envois `pending`
 *      par chunks (respect pause/cancel, statuts sent/failed/suppressed,
 *      rate limit tenant respecté) ;
 *   3. AUTO-FINISH : le job termine la campagne (`finished` +
 *      `CampaignFinished`) quand plus aucun envoi n'est pending/queued.
 */
final class ProcessCampaignSends extends Command
{
    protected $signature = 'crm:process-campaign-sends';

    protected $description = 'Demarre les campagnes CRM scheduled dues et draine les envois pending des campagnes email running (auto-finish inclus).';

    public function handle(TenantManager $tenants, CampaignService $campaigns): int
    {
        $started = $this->autoStartDueCampaigns($tenants, $campaigns);
        $drained = $this->dispatchRunningEmailCampaigns();

        $this->info(sprintf('Campagnes auto-demarrees : %d ; jobs de drainage email mis en file : %d.', $started, $drained));

        return self::SUCCESS;
    }

    private function autoStartDueCampaigns(TenantManager $tenants, CampaignService $campaigns): int
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, CrmCampaign> $due */
        $due = CrmCampaign::query()
            ->withoutGlobalScopes()
            ->where('status', CampaignStatus::Scheduled->value)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('id')
            ->get();

        $started = 0;

        foreach ($due as $campaign) {
            /** @var Company|null $company */
            $company = Company::query()->find($campaign->company_id);

            if ($company === null) {
                continue;
            }

            try {
                $tenants->withinTenant($company, function () use ($campaigns, $campaign): void {
                    $campaigns->start($campaign, null);
                });
                $started++;
            } catch (ValidationException $e) {
                // Campagne non démarrable (contenu email manquant, audience
                // vide…) : journalisée, jamais bloquante pour les autres.
                Log::warning('crm:process-campaign-sends — auto-start refusé', [
                    'campaign_id' => $campaign->id,
                    'company_id' => $campaign->company_id,
                    'errors' => $e->errors(),
                ]);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $started;
    }

    private function dispatchRunningEmailCampaigns(): int
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, CrmCampaign> $running */
        $running = CrmCampaign::query()
            ->withoutGlobalScopes()
            ->where('status', CampaignStatus::Running->value)
            ->where('channel', 'email')
            ->orderBy('id')
            ->get();

        foreach ($running as $campaign) {
            // Drainage + auto-finish : le job traite les pending restants et
            // termine la campagne quand plus rien n'est pending/queued.
            ProcessCampaignSendsJob::dispatch($campaign->company_id, $campaign->id);
        }

        return $running->count();
    }
}
