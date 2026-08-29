<?php

declare(strict_types=1);

namespace App\Modules\CRM\Providers;

use App\Modules\CRM\Application\Listeners\PropagateConsentRevocation;
use App\Modules\CRM\Domain\Contracts\CampaignConsentCheckerInterface;
use App\Modules\CRM\Domain\Contracts\EmailProviderInterface;
use App\Modules\CRM\Domain\Contracts\ChannelAdapterContract;
use App\Modules\CRM\Domain\Contracts\CrmChannelMessageRepositoryInterface;
use App\Modules\CRM\Domain\Enums\CrmChannelType;
use App\Modules\CRM\Infrastructure\Integrations\WhatsApp\WhatsAppAdapter;
use App\Modules\CRM\Infrastructure\Integrations\Sms\SmsAdapter;
use App\Modules\CRM\Infrastructure\Integrations\WhatsApp\WhatsAppCloudApiClient;
use App\Modules\CRM\Infrastructure\Repositories\CrmChannelMessageRepository;
use App\Modules\CRM\Infrastructure\Services\ConsentTableCampaignConsentChecker;
use App\Modules\CRM\Infrastructure\Services\CrmChannelService;
use App\Modules\CRM\Infrastructure\Services\LogEmailProvider;
use App\Modules\CRM\Infrastructure\Services\MailEmailProvider;

use App\Modules\CRM\Domain\Contracts\CrmImportRepositoryInterface;
use App\Modules\CRM\Domain\Contracts\CrmImportRowPersisterInterface;
use App\Modules\CRM\Domain\Contracts\CrmLeadRepositoryInterface;
use App\Modules\CRM\Domain\Events\CrmConsentRevoked;
use App\Modules\CRM\Domain\Contracts\SegmentContactSourceInterface;
use App\Modules\CRM\Domain\Models\CrmAccount;
use App\Modules\CRM\Domain\Models\CrmImport;
use App\Modules\CRM\Domain\Models\CrmLead;
use App\Modules\CRM\Infrastructure\Repositories\CrmImportRepository;
use App\Modules\CRM\Infrastructure\Repositories\CrmLeadRepository;
use App\Modules\CRM\Infrastructure\Services\CrmContactSegmentSource;
use App\Modules\CRM\Infrastructure\Services\CrmImportRowPersister;
use App\Modules\CRM\Infrastructure\Services\CrmOutboxConsumerRegistry;
use App\Modules\CRM\Infrastructure\Services\CrmOutboxPublisher;
use App\Modules\CRM\Policies\CrmImportPolicy;
use App\Modules\CRM\Policies\CrmLeadPolicy;
use App\Modules\CRM\Policies\CrmMergePolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Foundation\Application;

/**
 * Provider du module CRM client — #5714/#5717/#5718/#5741 (import CSV,
 * conversion, déduplication, outbox) + #5722 (consentements), #5723
 * (segments), #5725/#5727/#5728/#5729 (canaux, imports, automatisations).
 *
 * Enregistre les ports & adapters du module (contrats → implémentations),
 * les Policies métier et les écouteurs locaux (Event::listen, anti-
 * collision). Le module CRM client est strictement isolé du CRM commercial
 * Platform/Marketing (ADR-CRM-001, garde d'isolation #5584).
 *
 * Le squelette DDD ratifié par l'ADR-CRM-DUAL-CONTEXTS est remplacé par les
 * implémentations métier au fil des issues CRM-V0/V1.
 */
class CrmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CrmChannelMessageRepositoryInterface::class, CrmChannelMessageRepository::class);

        $this->app->singleton(WhatsAppCloudApiClient::class);
        $this->app->singleton(WhatsAppAdapter::class);
        $this->app->singleton(SmsAdapter::class);

        // Registre des adaptateurs par type de canal (#5727) : chaque
        // nouveau canal (sms, email…) s'ajoute ici sans coupler le CRM.
        $this->app->singleton(CrmChannelService::class, function ($app): CrmChannelService {
            /** @var array<string, ChannelAdapterContract> $adapters */
            $adapters = [
                CrmChannelType::WHATSAPP => $app->make(WhatsAppAdapter::class),
                CrmChannelType::SMS => $app->make(SmsAdapter::class),
            ];

            return new CrmChannelService(
                adapters: $adapters,
                messages: $app->make(CrmChannelMessageRepositoryInterface::class),
                consentGuard: $app->make(\App\Modules\CRM\Infrastructure\Services\CrmConsentGuard::class),
                quotaService: $app->make(\App\Modules\CRM\Infrastructure\Services\CrmQuotaService::class),
            );
        });

        $this->app->singleton(CrmImportRepositoryInterface::class, CrmImportRepository::class);
        $this->app->singleton(CrmImportRowPersisterInterface::class, CrmImportRowPersister::class);
        $this->app->singleton(CrmLeadRepositoryInterface::class, CrmLeadRepository::class);
        $this->app->singleton(CrmChannelRegistry::class, function ($app): CrmChannelRegistry {
            return new CrmChannelRegistry([
                CrmChannelType::WHATSAPP => $app->make(WhatsAppAdapter::class),
                CrmChannelType::SMS => $app->make(SmsAdapter::class),
            ]);
        });

        // #5728 — moteur d'automatisations (actions bornées par whitelist).
        $this->app->singleton(AutomationEngine::class, function ($app): AutomationEngine {
            return new AutomationEngine(
                actions: [
                    \App\Modules\CRM\Domain\Enums\CrmAutomationActionType::SEND_WHATSAPP => $app->make(\App\Modules\CRM\Application\Actions\AutomationActions\SendWhatsAppAction::class),
                    \App\Modules\CRM\Domain\Enums\CrmAutomationActionType::SEND_SMS => $app->make(\App\Modules\CRM\Application\Actions\AutomationActions\SendSmsAction::class),
                    \App\Modules\CRM\Domain\Enums\CrmAutomationActionType::SEND_EMAIL => $app->make(\App\Modules\CRM\Application\Actions\AutomationActions\SendEmailAction::class),
                    \App\Modules\CRM\Domain\Enums\CrmAutomationActionType::CREATE_TASK => $app->make(\App\Modules\CRM\Application\Actions\AutomationActions\CreateTaskAction::class),
                    \App\Modules\CRM\Domain\Enums\CrmAutomationActionType::HTTP_WEBHOOK => $app->make(\App\Modules\CRM\Application\Actions\AutomationActions\HttpWebhookAction::class),
                ],
                evaluator: $app->make(CrmConditionEvaluator::class),
            );
        });

        $this->app->singleton(CrmOutboxPublisher::class);
        $this->app->singleton(CrmOutboxConsumerRegistry::class);

        // #5723 — source de contacts par défaut pour l'évaluation des segments.
        $this->app->bind(SegmentContactSourceInterface::class, CrmContactSegmentSource::class);

        // #5726 — fournisseur email interchangeable (log | mail).
        $this->app->bind(EmailProviderInterface::class, function (): EmailProviderInterface {
            $provider = config('crm.email.provider', 'log');

            return is_string($provider) && $provider === 'mail'
                ? new MailEmailProvider
                : new LogEmailProvider;
        });

        // #5724 — garde de consentement avant tout envoi de campagne.
        $this->app->bind(CampaignConsentCheckerInterface::class, ConsentTableCampaignConsentChecker::class);
    }

    public function boot(): void
    {
        // Commandes artisan du module CRM (#5729) : auto-découverte hors
        // app/Console/Commands → enregistrement explicite.
        $this->commands([
            \App\Modules\CRM\Console\Commands\CleanupCrmExports::class,
        ]);

        Gate::policy(CrmImport::class, CrmImportPolicy::class);
        Gate::policy(CrmLead::class, CrmLeadPolicy::class);
        Gate::policy(CrmAccount::class, CrmMergePolicy::class);

        // #5722 — propagation du retrait de consentement vers les campagnes
        // (#5724) : annulation des envois pending/queued du contact.
        Event::listen(CrmConsentRevoked::class, PropagateConsentRevocation::class);
    }
}
