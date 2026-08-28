<?php

declare(strict_types=1);

namespace App\Modules\CRM\Providers;

use App\Modules\CRM\Domain\Contracts\ChannelAdapterContract;
use App\Modules\CRM\Domain\Contracts\CrmChannelMessageRepositoryInterface;
use App\Modules\CRM\Domain\Enums\CrmChannelType;
use App\Modules\CRM\Infrastructure\Integrations\WhatsApp\WhatsAppAdapter;
use App\Modules\CRM\Infrastructure\Integrations\WhatsApp\WhatsAppCloudApiClient;
use App\Modules\CRM\Infrastructure\Repositories\CrmChannelMessageRepository;
use App\Modules\CRM\Infrastructure\Services\CrmChannelService;

use App\Modules\CRM\Domain\Contracts\CrmImportRepositoryInterface;
use App\Modules\CRM\Domain\Contracts\CrmImportRowPersisterInterface;
use App\Modules\CRM\Domain\Contracts\CrmLeadRepositoryInterface;
use App\Modules\CRM\Infrastructure\Repositories\CrmImportRepository;
use App\Modules\CRM\Infrastructure\Repositories\CrmLeadRepository;
use App\Modules\CRM\Infrastructure\Services\CrmImportRowPersister;
use App\Modules\CRM\Infrastructure\Services\CrmOutboxConsumerRegistry;
use App\Modules\CRM\Infrastructure\Services\CrmOutboxPublisher;
use App\Modules\CRM\Policies\CrmImportPolicy;
use App\Modules\CRM\Policies\CrmLeadPolicy;
use App\Modules\CRM\Policies\CrmMergePolicy;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;use Illuminate\Support\ServiceProvider;

/**
 * #5714/#5717/#5718/#5741 — Provider du module CRM (import CSV, conversion,
 * déduplication, outbox).
 *
 * Squelette DDD ratifié par l'ADR-CRM-DUAL-CONTEXTS : le CRM client est un
 * module tenant-scoped distinct du CRM commercial Leopardo (Platform/
 * Marketing). Les couches Application/Domain/Infrastructure/Interfaces se
 * remplissent au fil des issues CRM-V0-04+ ; les canaux de communication
 * tenant (WhatsApp/SMS/email) sont livrés par les issues CRM-V1 (#5725+).

 * Enregistre les ports & adapters du module (contrats → implémentations) et
 * les Policies métier. Le module CRM client est strictement isolé du CRM
 * commercial Platform/Marketing (ADR-CRM-001, garde d'isolation #5584).
 * (Squelette CRM-V0-03 #5707 remplacé par les implémentations métier.) */
class CrmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CrmChannelMessageRepositoryInterface::class, CrmChannelMessageRepository::class);

        $this->app->singleton(WhatsAppCloudApiClient::class);
        $this->app->singleton(WhatsAppAdapter::class);
        $this->app->singleton(\App\Modules\CRM\Infrastructure\Integrations\Sms\SmsAdapter::class);

        // Registre des adaptateurs par type de canal (#5727) : chaque
        // nouveau canal (sms, email…) s'ajoute ici sans coupler le CRM.
        $this->app->singleton(\App\Modules\CRM\Infrastructure\Services\CrmChannelRegistry::class, function ($app): \App\Modules\CRM\Infrastructure\Services\CrmChannelRegistry {
            return new \App\Modules\CRM\Infrastructure\Services\CrmChannelRegistry([
                CrmChannelType::WHATSAPP => $app->make(WhatsAppAdapter::class),
                CrmChannelType::SMS => $app->make(\App\Modules\CRM\Infrastructure\Integrations\Sms\SmsAdapter::class),
            ]);
        });

        $this->app->singleton(CrmChannelService::class, function ($app): CrmChannelService {
            /** @var array<string, ChannelAdapterContract> $adapters */
            $adapters = [
                CrmChannelType::WHATSAPP => $app->make(WhatsAppAdapter::class),
                CrmChannelType::SMS => $app->make(\App\Modules\CRM\Infrastructure\Integrations\Sms\SmsAdapter::class),
            ];

            return new CrmChannelService(
                adapters: $adapters,
                messages: $app->make(CrmChannelMessageRepositoryInterface::class),
                consentGuard: $app->make(\App\Modules\CRM\Infrastructure\Services\CrmConsentGuard::class),
                quotaService: $app->make(\App\Modules\CRM\Infrastructure\Services\CrmQuotaService::class),
            );
        });

        // Automatisations CRM (#5728) : actions terminales enregistrées par
        // type — le moteur ne sait exécuter QUE ces types (whitelist).
        $this->app->singleton(\App\Modules\CRM\Infrastructure\Services\AutomationEngine::class, function ($app): \App\Modules\CRM\Infrastructure\Services\AutomationEngine {
            return new \App\Modules\CRM\Infrastructure\Services\AutomationEngine(
                actions: [
                    \App\Modules\CRM\Domain\Enums\CrmAutomationActionType::SEND_WHATSAPP => $app->make(\App\Modules\CRM\Application\Actions\AutomationActions\SendWhatsAppAction::class),
                    \App\Modules\CRM\Domain\Enums\CrmAutomationActionType::SEND_SMS => $app->make(\App\Modules\CRM\Application\Actions\AutomationActions\SendSmsAction::class),
                    \App\Modules\CRM\Domain\Enums\CrmAutomationActionType::SEND_EMAIL => $app->make(\App\Modules\CRM\Application\Actions\AutomationActions\SendEmailAction::class),
                    \App\Modules\CRM\Domain\Enums\CrmAutomationActionType::CREATE_TASK => $app->make(\App\Modules\CRM\Application\Actions\AutomationActions\CreateTaskAction::class),
                    \App\Modules\CRM\Domain\Enums\CrmAutomationActionType::HTTP_WEBHOOK => $app->make(\App\Modules\CRM\Application\Actions\AutomationActions\HttpWebhookAction::class),
                ],
                evaluator: $app->make(\App\Modules\CRM\Infrastructure\Services\CrmConditionEvaluator::class),
            );
        });

        $this->app->singleton(CrmImportRepositoryInterface::class, CrmImportRepository::class);
        $this->app->singleton(CrmImportRowPersisterInterface::class, CrmImportRowPersister::class);
        $this->app->singleton(CrmLeadRepositoryInterface::class, CrmLeadRepository::class);
        $this->app->singleton(CrmOutboxPublisher::class);
        $this->app->singleton(CrmOutboxConsumerRegistry::class);    }

    public function boot(): void
    {
        // Routes chargées via require dans routes/api.php
        // (routes/modules/crm.php — issues #5725/#5727/#5728/#5729).

        Gate::policy(\App\Modules\CRM\Domain\Models\CrmImport::class, CrmImportPolicy::class);
        Gate::policy(\App\Modules\CRM\Domain\Models\CrmLead::class, CrmLeadPolicy::class);
        Gate::policy(\App\Modules\CRM\Domain\Models\CrmAccount::class, CrmMergePolicy::class);    }
}
