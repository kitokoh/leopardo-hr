<?php

declare(strict_types=1);

namespace App\Modules\CRM\Providers;

use App\Modules\CRM\Application\Listeners\PropagateConsentRevocation;
use App\Modules\CRM\Domain\Contracts\CrmImportRepositoryInterface;
use App\Modules\CRM\Domain\Contracts\CrmImportRowPersisterInterface;
use App\Modules\CRM\Domain\Contracts\CrmLeadRepositoryInterface;
use App\Modules\CRM\Domain\Events\CrmConsentRevoked;
use App\Modules\CRM\Domain\Models\CrmAccount;
use App\Modules\CRM\Domain\Models\CrmImport;
use App\Modules\CRM\Domain\Models\CrmLead;
use App\Modules\CRM\Infrastructure\Repositories\CrmImportRepository;
use App\Modules\CRM\Infrastructure\Repositories\CrmLeadRepository;
use App\Modules\CRM\Infrastructure\Services\CrmImportRowPersister;
use App\Modules\CRM\Infrastructure\Services\CrmOutboxConsumerRegistry;
use App\Modules\CRM\Infrastructure\Services\CrmOutboxPublisher;
use App\Modules\CRM\Policies\CrmImportPolicy;
use App\Modules\CRM\Policies\CrmLeadPolicy;
use App\Modules\CRM\Policies\CrmMergePolicy;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Provider du module CRM client — #5714/#5717/#5718/#5741 (import CSV,
 * conversion, déduplication, outbox) + #5722 (consentements).
 *
 * Enregistre les ports & adapters du module (contrats → implémentations),
 * les Policies métier et les écouteurs locaux (Event::listen, anti-
 * collision). Le module CRM client est strictement isolé du CRM commercial
 * Platform/Marketing (ADR-CRM-001, garde d'isolation #5584).
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
    }

    public function boot(): void
    {
        Gate::policy(CrmImport::class, CrmImportPolicy::class);
        Gate::policy(CrmLead::class, CrmLeadPolicy::class);
        Gate::policy(CrmAccount::class, CrmMergePolicy::class);

        // #5722 — propagation du retrait de consentement vers les campagnes
        // (#5724) : annulation des envois pending/queued du contact.
        Event::listen(CrmConsentRevoked::class, PropagateConsentRevocation::class);
    }
}
