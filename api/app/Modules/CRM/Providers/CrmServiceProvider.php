<?php

declare(strict_types=1);

namespace App\Modules\CRM\Providers;

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
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * #5714/#5717/#5718/#5741 — Provider du module CRM (import CSV, conversion,
 * déduplication, outbox).
 *
 * Enregistre les ports & adapters du module (contrats → implémentations) et
 * les Policies métier. Le module CRM client est strictement isolé du CRM
 * commercial Platform/Marketing (ADR-CRM-001, garde d'isolation #5584).
 * (Squelette CRM-V0-03 #5707 remplacé par les implémentations métier.)
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
        Gate::policy(\App\Modules\CRM\Domain\Models\CrmImport::class, CrmImportPolicy::class);
        Gate::policy(\App\Modules\CRM\Domain\Models\CrmLead::class, CrmLeadPolicy::class);
        Gate::policy(\App\Modules\CRM\Domain\Models\CrmAccount::class, CrmMergePolicy::class);
        Gate::policy(\App\Modules\CRM\Domain\Models\CrmActivity::class, \App\Modules\CRM\Policies\CrmActivityPolicy::class);
        Gate::policy(\App\Modules\CRM\Domain\Models\CrmOpportunity::class, \App\Modules\CRM\Policies\CrmOpportunityPolicy::class);
        Gate::policy(\App\Modules\CRM\Domain\Models\CrmPipeline::class, \App\Modules\CRM\Policies\CrmPipelinePolicy::class);
        Gate::policy(\App\Modules\CRM\Domain\Models\CrmPipelineStage::class, \App\Modules\CRM\Policies\CrmPipelineStagePolicy::class);
    }
}
