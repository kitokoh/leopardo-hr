<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Providers;

use App\Modules\RestaurantManager\Console\Commands\ActivateRestaurantManagerCommand;
use App\Modules\RestaurantManager\Console\Commands\RestaurantStockAlertCommand;
use App\Modules\RestaurantManager\Console\Commands\SeedRestaurantDemoCommand;
use App\Modules\RestaurantManager\Domain\Contracts\RestaurantBranchRepositoryInterface;
use App\Modules\RestaurantManager\Domain\Contracts\RestaurantOrderRepositoryInterface;
use App\Modules\RestaurantManager\Domain\Contracts\RestaurantPosSessionRepositoryInterface;
use App\Modules\RestaurantManager\Domain\Contracts\RestaurantReservationRepositoryInterface;
use App\Modules\RestaurantManager\Domain\Contracts\RestaurantStockLevelRepositoryInterface;
use App\Modules\RestaurantManager\Domain\Contracts\SolutionManifest;
use App\Modules\RestaurantManager\Domain\Manifests\RestaurantManagerManifest;
use App\Modules\RestaurantManager\Domain\Models\RestaurantDeliveryZone;
use App\Modules\RestaurantManager\Domain\Models\RestaurantInventoryCount;
use App\Modules\RestaurantManager\Domain\Models\RestaurantInventoryMovement;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPurchaseOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantReceiving;
use App\Modules\RestaurantManager\Domain\Models\RestaurantReservation;
use App\Modules\RestaurantManager\Domain\Models\RestaurantStockLevel;
use App\Modules\RestaurantManager\Infrastructure\Repositories\RestaurantBranchRepository;
use App\Modules\RestaurantManager\Infrastructure\Repositories\RestaurantOrderRepository;
use App\Modules\RestaurantManager\Infrastructure\Repositories\RestaurantPosSessionRepository;
use App\Modules\RestaurantManager\Infrastructure\Repositories\RestaurantReservationRepository;
use App\Modules\RestaurantManager\Infrastructure\Repositories\RestaurantStockLevelRepository;
use App\Modules\RestaurantManager\Policies\RestaurantDeliveryZonePolicy;
use App\Modules\RestaurantManager\Policies\RestaurantInventoryCountPolicy;
use App\Modules\RestaurantManager\Policies\RestaurantInventoryMovementPolicy;
use App\Modules\RestaurantManager\Policies\RestaurantPurchaseOrderPolicy;
use App\Modules\RestaurantManager\Policies\RestaurantReceivingPolicy;
use App\Modules\RestaurantManager\Policies\RestaurantReservationPolicy;
use App\Modules\RestaurantManager\Policies\RestaurantStockLevelPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Provider du module RestaurantManager (BC-25 RESTAURANT).
 *
 * Fondations de la verticale « Restauration » (RESTO-101, issue #6158) :
 * module DDD conforme aux conventions (api/stubs/module-template), porte
 * d'entrée de l'outillage opérationnel du restaurateur (POS & caisse,
 * commandes, réservations, stock/COGS, livraison, fidélité, rapports).
 *
 * `register()` enregistre les ports & adapters du module (contrats →
 * implémentations) et le manifest de solution ; les Policies métier seront
 * enregistrées dans `boot()` au fil des lots API (épic 3xx).
 *
 * L'activation par tenant passe par le feature flag `restaurantmanager`
 * (companies.features) — voir EnsureRestaurantManagerModuleMiddleware
 * (RESTO-102) et ActivateRestaurantManagerAction (RESTO-105).
 */
class RestaurantManagerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SolutionManifest::class, RestaurantManagerManifest::class);

        // Ports & adapters de persistance (RESTO-215, issue #6180) : les
        // implémentations Eloquent sont résolues en singleton derrière leur
        // contrat, conformément au pattern CrmLeadRepository.
        $this->app->singleton(RestaurantBranchRepositoryInterface::class, RestaurantBranchRepository::class);
        $this->app->singleton(RestaurantPosSessionRepositoryInterface::class, RestaurantPosSessionRepository::class);
        $this->app->singleton(RestaurantOrderRepositoryInterface::class, RestaurantOrderRepository::class);
        $this->app->singleton(RestaurantStockLevelRepositoryInterface::class, RestaurantStockLevelRepository::class);
        $this->app->singleton(RestaurantReservationRepositoryInterface::class, RestaurantReservationRepository::class);

        // RESTO-105 (#6162) — activation tenant (flag + référentiel) ;
        // RESTO-107 (#6164) — seed de démonstration idempotent ;
        // RESTO-505 (#6204) — détection des alertes de seuil de stock.
        $this->commands([
            ActivateRestaurantManagerCommand::class,
            SeedRestaurantDemoCommand::class,
            RestaurantStockAlertCommand::class,
        ]);
    }

    public function boot(): void
    {
        // Policies stock (RESTO-501, #6200) : niveaux de stock et journal des
        // mouvements — enregistrement explicite, pattern TravelAgency.
        Gate::policy(RestaurantStockLevel::class, RestaurantStockLevelPolicy::class);
        Gate::policy(RestaurantInventoryMovement::class, RestaurantInventoryMovementPolicy::class);

        // Policies achats & inventaires (RESTO-502..504, #6201/#6202/#6203).
        Gate::policy(RestaurantPurchaseOrder::class, RestaurantPurchaseOrderPolicy::class);
        Gate::policy(RestaurantReceiving::class, RestaurantReceivingPolicy::class);
        Gate::policy(RestaurantInventoryCount::class, RestaurantInventoryCountPolicy::class);

        // Policies réservations & livraison (RESTO-601/#6206, RESTO-604/#6209).
        Gate::policy(RestaurantReservation::class, RestaurantReservationPolicy::class);
        Gate::policy(RestaurantDeliveryZone::class, RestaurantDeliveryZonePolicy::class);
    }
}
