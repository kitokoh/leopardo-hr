<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Providers;

use App\Modules\SearchIntelligence\Application\Services\SeoIssueWorkflow;
use App\Modules\SearchIntelligence\Domain\Contracts\LeadMetricsProviderInterface;
use App\Modules\SearchIntelligence\Domain\Contracts\PageFetcherInterface;
use App\Modules\SearchIntelligence\Infrastructure\Services\HttpPageFetcher;
use App\Modules\SearchIntelligence\Infrastructure\Services\NullLeadMetricsProvider;
use App\Modules\SearchIntelligence\Infrastructure\Services\SearchIntelOverviewService;
use App\Modules\SearchIntelligence\Infrastructure\Services\SeoPageAnalyzer;
use App\Modules\SearchIntelligence\Infrastructure\Services\SeoPageScanner;
use Illuminate\Support\ServiceProvider;

/**
 * GSI-1 (#8512) / GSI-2 (#8513) — provider du module SearchIntelligence.
 *
 * Les routes sont chargées depuis `routes/api.php` (groupe `platform`, garde
 * `platform.permission`), pas par `loadRoutesFrom` : c'est la convention du
 * dépôt (cf. `GeoServiceProvider`, `DeliveryServiceProvider`).
 *
 * Deux contrats sont branchés sur des implémentations **fail-closed** :
 *  - `LeadMetricsProviderInterface` → aucune métrique de leads tant que le
 *    BC-11 GROWTH n'a pas exposé la sienne ;
 *  - `PageFetcherInterface` → HTTP en lecture seule uniquement.
 */
class SearchIntelligenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LeadMetricsProviderInterface::class, NullLeadMetricsProvider::class);

        // Le fetcher est un singleton : une instance par requête suffit et
        // évite de recréer un client HTTP à chaque page scannée.
        $this->app->singleton(PageFetcherInterface::class, function (): PageFetcherInterface {
            return new HttpPageFetcher((int) config('search_intel.scan.timeout', 10));
        });

        $this->app->bind(SeoPageAnalyzer::class);
        $this->app->bind(SeoPageScanner::class);
        $this->app->bind(SeoIssueWorkflow::class);
        $this->app->singleton(SearchIntelOverviewService::class);
    }

    public function boot(): void
    {
        // Aucun boot actif : le module expose des routes et des services
        // passifs, sans écouteur d'événement ni commande artisan.
    }
}
