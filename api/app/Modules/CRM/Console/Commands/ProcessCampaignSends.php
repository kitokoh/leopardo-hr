<?php

declare(strict_types=1);

namespace App\Modules\CRM\Console\Commands;

use App\Modules\CRM\Domain\Enums\CampaignSendStatus;
use App\Modules\CRM\Domain\Enums\CampaignStatus;
use App\Modules\CRM\Domain\Models\CrmCampaign;
use App\Modules\CRM\Infrastructure\Jobs\ProcessCampaignSendsJob;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Module CRM — Issue #7751 (worker des campagnes email).
 *
 * Filet de sécurité planifié (5 min, voir routes/console.php), tous tenants
 * confondus (même pattern que `marketing:publish-scheduled-posts`) :
 *   1. campagnes email `scheduled` dont l'échéance est passée → job
 *      (auto-start dans le contexte tenant du job) ;
 *   2. campagnes email `running` avec des envois pending → job (reprise
 *      après crash de worker, release quota, listener perdu…).
 *
 * Le job `ProcessCampaignSendsJob` établit lui-même le contexte tenant
 * (`EnsureTenantContext`) et réclame chaque send atomiquement — cette
 * commande peut donc recouper le listener `DispatchCampaignSends` sans
 * double envoi.
 */
class ProcessCampaignSends extends Command
{
    protected $signature = 'crm:process-campaign-sends {--limit=20 : Nombre maximum de campagnes traitées par exécution}';

    protected $description = 'Démarre les campagnes email planifiées dues et draine les envois pending des campagnes running';

    public function handle(): int
    {
        if (! Schema::hasTable('crm_campaigns')) {
            $this->info('crm:process-campaign-sends — tables campagnes absentes, rien à faire.');

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));

        $due = CrmCampaign::query()
            ->withoutGlobalScopes()
            ->where('channel', 'email')
            ->where('status', CampaignStatus::Scheduled->value)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->limit($limit)
            ->get();

        $running = CrmCampaign::query()
            ->withoutGlobalScopes()
            ->where('channel', 'email')
            ->where('status', CampaignStatus::Running->value)
            ->whereHas('sends', function (Builder $query): void {
                /** @var Builder<\App\Modules\CRM\Domain\Models\CrmCampaignSend> $query */
                $query->withoutGlobalScopes()
                    ->where('status', CampaignSendStatus::Pending->value);
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $campaigns = $due->concat($running)->unique('id');

        $dispatched = 0;
        $failed = 0;

        foreach ($campaigns as $campaign) {
            try {
                ProcessCampaignSendsJob::dispatch((string) $campaign->company_id, $campaign->id);
                $dispatched++;
            } catch (Throwable $e) {
                $failed++;

                Log::error('crm:process-campaign-sends — échec de dispatch', [
                    'campaign_id' => $campaign->id,
                    'company_id' => $campaign->company_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("crm:process-campaign-sends — dues: {$due->count()}, running: {$running->count()}, dispatchés: {$dispatched}, échecs: {$failed}.");

        return self::SUCCESS;
    }
}
