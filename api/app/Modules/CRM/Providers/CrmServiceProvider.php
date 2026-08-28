<?php

declare(strict_types=1);

namespace App\Modules\CRM\Providers;

use App\Modules\CRM\Domain\Contracts\CampaignConsentCheckerInterface;
use App\Modules\CRM\Domain\Contracts\CrmImportRepositoryInterface;
use App\Modules\CRM\Domain\Contracts\CrmImportRowPersisterInterface;
use App\Modules\CRM\Domain\Contracts\CrmLeadRepositoryInterface;
use App\Modules\CRM\Domain\Contracts\EmailProviderInterface;
use App\Modules\CRM\Domain\Models\CrmAccount;
use App\Modules\CRM\Domain\Models\CrmImport;
use App\Modules\CRM\Domain\Models\CrmLead;
use App\Modules\CRM\Infrastructure\Repositories\CrmImportRepository;
use App\Modules\CRM\Infrastructure\Repositories\CrmLeadRepository;
use App\Modules\CRM\Infrastructure\Services\ConsentTableCampaignConsentChecker;
use App\Modules\CRM\Infrastructure\Services\CrmImportRowPersister;
use App\Modules\CRM\Infrastructure\Services\CrmOutboxConsumerRegistry;
use App\Modules\CRM\Infrastructure\Services\CrmOutboxPublisher;
use App\Modules\CRM\Infrastructure\Services\LogEmailProvider;
use App\Modules\CRM\Infrastructure\Services\MailEmailProvider;
use App\Modules\CRM\Policies\CrmImportPolicy;
use App\Modules\CRM\Policies\CrmLeadPolicy;
use App\Modules\CRM\Policies\CrmMergePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Provider du module CRM client — #5714/#5717/#5718/#5741 (import CSV,
 * conversion, déduplication, outbox) + #5726 (canal email).
 *
 * Enregistre les ports & adapters du module (contrats → implémentations)
 * et les Policies métier. Le module CRM client est strictement isolé du
 * CRM commercial Platform/Marketing (ADR-CRM-001, garde d'isolation #5584).
 */
class CrmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CrmImportRepositoryInterface::class, CrmImportRepository::class);
        $this->app->singleton(CrmImportRowPersisterInterface::class, CrmImportRowPersister::class);
        $this->app->singleton(CrmLeadRepositoryInterface::class, CrmLeadRepository::class);
        $this->app->singleton(CrmOutboxPublisher::class);
        $this->app->singleton(CrmOutboxConsumerRegistry::class);

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
        Gate::policy(CrmImport::class, CrmImportPolicy::class);
        Gate::policy(CrmLead::class, CrmLeadPolicy::class);
        Gate::policy(CrmAccount::class, CrmMergePolicy::class);
    }
}
