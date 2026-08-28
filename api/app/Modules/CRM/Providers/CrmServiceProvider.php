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
use Illuminate\Support\ServiceProvider;

/**
 * CRM Client (interne tenant) — provider du module (issue #5707, CRM-V0-03).
 *
 * Squelette DDD ratifié par l'ADR-CRM-DUAL-CONTEXTS : le CRM client est un
 * module tenant-scoped distinct du CRM commercial Leopardo (Platform/
 * Marketing). Les couches Application/Domain/Infrastructure/Interfaces se
 * remplissent au fil des issues CRM-V0-04+ ; les canaux de communication
 * tenant (WhatsApp/SMS/email) sont livrés par les issues CRM-V1 (#5725+).
 */
class CrmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CrmChannelMessageRepositoryInterface::class, CrmChannelMessageRepository::class);

        $this->app->singleton(WhatsAppCloudApiClient::class);
        $this->app->singleton(WhatsAppAdapter::class);

        // Registre des adaptateurs par type de canal (#5727) : chaque
        // nouveau canal (sms, email…) s'ajoute ici sans coupler le CRM.
        $this->app->singleton(CrmChannelService::class, function ($app): CrmChannelService {
            /** @var array<string, ChannelAdapterContract> $adapters */
            $adapters = [
                CrmChannelType::WHATSAPP => $app->make(WhatsAppAdapter::class),
            ];

            return new CrmChannelService(
                adapters: $adapters,
                messages: $app->make(CrmChannelMessageRepositoryInterface::class),
                consentGuard: $app->make(\App\Modules\CRM\Infrastructure\Services\CrmConsentGuard::class),
                quotaService: $app->make(\App\Modules\CRM\Infrastructure\Services\CrmQuotaService::class),
            );
        });
    }

    public function boot(): void
    {
        // Commandes artisan du module CRM (#5729) : auto-découverte hors
        // app/Console/Commands → enregistrement explicite.
        $this->commands([
            \App\Modules\CRM\Console\Commands\CleanupCrmExports::class,
        ]);

        // Routes chargées via require dans routes/api.php
        // (routes/modules/crm.php — issues #5725/#5727/#5728/#5729).
    }
}
