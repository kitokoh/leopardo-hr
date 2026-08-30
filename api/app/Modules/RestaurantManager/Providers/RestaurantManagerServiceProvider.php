<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Providers;

use App\Modules\RestaurantManager\Application\Services\CogsCalculator;
use App\Modules\RestaurantManager\Application\Services\StockAlertService;
use App\Modules\RestaurantManager\Application\Services\StockDecrementer;
use App\Modules\RestaurantManager\Console\Commands\ActivateRestaurantManagerCommand;
use App\Modules\RestaurantManager\Console\Commands\SeedRestaurantDemoCommand;
use App\Modules\RestaurantManager\Console\Commands\StockAlertsCommand;
use App\Modules\RestaurantManager\Domain\Contracts\RestaurantBranchRepositoryInterface;
use App\Modules\RestaurantManager\Domain\Contracts\RestaurantOrderRepositoryInterface;
use App\Modules\RestaurantManager\Domain\Contracts\RestaurantPosSessionRepositoryInterface;
use App\Modules\RestaurantManager\Domain\Contracts\RestaurantReservationRepositoryInterface;
use App\Modules\RestaurantManager\Domain\Contracts\RestaurantStockLevelRepositoryInterface;
use App\Modules\RestaurantManager\Domain\Contracts\SolutionManifest;
use App\Modules\RestaurantManager\Domain\Manifests\RestaurantManagerManifest;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantCategory;
use App\Modules\RestaurantManager\Domain\Models\RestaurantHour;
use App\Modules\RestaurantManager\Domain\Models\RestaurantIngredient;
use App\Modules\RestaurantManager\Domain\Models\RestaurantInventoryCount;
use App\Modules\RestaurantManager\Domain\Models\RestaurantInventoryMovement;
use App\Modules\RestaurantManager\Domain\Models\RestaurantMenu;
use App\Modules\RestaurantManager\Domain\Models\RestaurantMenuItem;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrderPayment;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPosSession;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProductIngredient;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPurchaseOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantReceiving;
use App\Modules\RestaurantManager\Domain\Models\RestaurantRefund;
use App\Modules\RestaurantManager\Domain\Models\RestaurantReservation;
use App\Modules\RestaurantManager\Domain\Models\RestaurantStockLevel;
use App\Modules\RestaurantManager\Domain\Models\RestaurantSupplier;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTable;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTableSession;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTaxRate;
use App\Modules\RestaurantManager\Domain\Models\RestaurantUnit;
use App\Modules\RestaurantManager\Domain\Models\RestaurantZone;
use App\Modules\RestaurantManager\Infrastructure\Repositories\RestaurantBranchRepository;
use App\Modules\RestaurantManager\Infrastructure\Repositories\RestaurantOrderRepository;
use App\Modules\RestaurantManager\Infrastructure\Repositories\RestaurantPosSessionRepository;
use App\Modules\RestaurantManager\Infrastructure\Repositories\RestaurantReservationRepository;
use App\Modules\RestaurantManager\Infrastructure\Repositories\RestaurantStockLevelRepository;
use App\Modules\RestaurantManager\Infrastructure\Services\PaymentGatewayRegistry;
use App\Modules\RestaurantManager\Infrastructure\Services\PaymentGateways\CardPaymentGateway;
use App\Modules\RestaurantManager\Infrastructure\Services\PaymentGateways\CashPaymentGateway;
use App\Modules\RestaurantManager\Infrastructure\Services\PaymentGateways\MobileMoneyPaymentGateway;
use App\Modules\RestaurantManager\Infrastructure\Services\ReceivingService;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantOutboxPublisher;
use App\Modules\RestaurantManager\Infrastructure\Services\StockMovementService;
use App\Modules\RestaurantManager\Policies\RestaurantBranchPolicy;
use App\Modules\RestaurantManager\Policies\RestaurantCategoryPolicy;
use App\Modules\RestaurantManager\Policies\RestaurantHourPolicy;
use App\Modules\RestaurantManager\Policies\RestaurantIngredientPolicy;
use App\Modules\RestaurantManager\Policies\RestaurantInventoryCountPolicy;
use App\Modules\RestaurantManager\Policies\RestaurantInventoryMovementPolicy;
use App\Modules\RestaurantManager\Policies\RestaurantMenuItemPolicy;
use App\Modules\RestaurantManager\Policies\RestaurantMenuPolicy;
use App\Modules\RestaurantManager\Policies\RestaurantOrderPaymentPolicy;
use App\Modules\RestaurantManager\Policies\RestaurantOrderPolicy;
use App\Modules\RestaurantManager\Policies\RestaurantPosSessionPolicy;
use App\Modules\RestaurantManager\Policies\RestaurantProductIngredientPolicy;
use App\Modules\RestaurantManager\Policies\RestaurantProductPolicy;
use App\Modules\RestaurantManager\Policies\RestaurantPurchaseOrderPolicy;
use App\Modules\RestaurantManager\Policies\RestaurantReceivingPolicy;
use App\Modules\RestaurantManager\Policies\RestaurantRefundPolicy;
use App\Modules\RestaurantManager\Policies\RestaurantReservationPolicy;
use App\Modules\RestaurantManager\Policies\RestaurantStockLevelPolicy;
use App\Modules\RestaurantManager\Policies\RestaurantSupplierPolicy;
use App\Modules\RestaurantManager\Policies\RestaurantTablePolicy;
use App\Modules\RestaurantManager\Policies\RestaurantTableSessionPolicy;
use App\Modules\RestaurantManager\Policies\RestaurantTaxRatePolicy;
use App\Modules\RestaurantManager\Policies\RestaurantUnitPolicy;
use App\Modules\RestaurantManager\Policies\RestaurantZonePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use App\Modules\RestaurantManager\Console\Commands\RestaurantStockAlertCommand;
use App\Modules\RestaurantManager\Domain\Models\RestaurantDeliveryZone;
use App\Modules\RestaurantManager\Policies\RestaurantDeliveryZonePolicy;

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

        // RESTO-404 (#6191) — publication outbox des événements de la
        // verticale (après commit, idempotente, payload redigé).
        $this->app->singleton(RestaurantOutboxPublisher::class);

        // RESTO-406 (#6193) — registre des passerelles de paiement
        // (cash / carte / mobile money sandbox, aucun secret en dur).
        $this->app->singleton(PaymentGatewayRegistry::class, function (): PaymentGatewayRegistry {
            $registry = new PaymentGatewayRegistry();
            $registry->register(new CashPaymentGateway());
            $registry->register(new CardPaymentGateway());
            $registry->register(new MobileMoneyPaymentGateway());

            return $registry;
        });

        // RESTO-105 (#6162) — activation tenant (flag + référentiel) ;
        // RESTO-107 (#6164) — seed de démonstration idempotent ;
        // RESTO-505 (#6204) — alerte de seuil de stock (rescan complet).
        $this->commands([
            ActivateRestaurantManagerCommand::class,
            SeedRestaurantDemoCommand::class,
            StockAlertsCommand::class,
                    RestaurantStockAlertCommand::class,]);

        // RESTO-501..506 (#6200..#6205) — stock : le service de mouvements
        // (verrou SELECT FOR UPDATE, jamais négatif) dépend de l'alerte de
        // seuil (RESTO-505) ; réceptions (coût moyen pondéré) et décrément
        // de vente (RESTO-411) s'appuient dessus. COGS : calcul pur.
        $this->app->singleton(StockAlertService::class);
        $this->app->singleton(StockMovementService::class);
        $this->app->singleton(ReceivingService::class);
        $this->app->bind(StockDecrementer::class, function ($app): StockDecrementer {
            return new StockDecrementer(
                $app->make(StockMovementService::class),
                (bool) config('restaurantmanager.stock.block_on_insufficient', true),
            );
        });
        $this->app->singleton(CogsCalculator::class);
    }

    public function boot(): void
    {
        // Policies du référentiel branches/zones/tables (RESTO-301, #6182) :
        // enregistrement explicite des modèles métier vers leurs policies,
        // même pattern que TravelAgencyServiceProvider::boot().
        Gate::policy(RestaurantBranch::class, RestaurantBranchPolicy::class);
        Gate::policy(RestaurantZone::class, RestaurantZonePolicy::class);
        Gate::policy(RestaurantTable::class, RestaurantTablePolicy::class);

        // Policies du référentiel catalogue/recettes + matières/fiscalité
        // (RESTO-302/303, #6183/#6184) — même pattern d'enregistrement.
        Gate::policy(RestaurantCategory::class, RestaurantCategoryPolicy::class);
        Gate::policy(RestaurantProduct::class, RestaurantProductPolicy::class);
        Gate::policy(RestaurantProductIngredient::class, RestaurantProductIngredientPolicy::class);
        Gate::policy(RestaurantIngredient::class, RestaurantIngredientPolicy::class);
        Gate::policy(RestaurantUnit::class, RestaurantUnitPolicy::class);
        Gate::policy(RestaurantTaxRate::class, RestaurantTaxRatePolicy::class);

        // Policies du référentiel menus/items/horaires (RESTO-304, #6185) et
        // fournisseurs (RESTO-305, #6186) — même pattern d'enregistrement.
        Gate::policy(RestaurantMenu::class, RestaurantMenuPolicy::class);
        Gate::policy(RestaurantMenuItem::class, RestaurantMenuItemPolicy::class);
        Gate::policy(RestaurantHour::class, RestaurantHourPolicy::class);
        Gate::policy(RestaurantSupplier::class, RestaurantSupplierPolicy::class);

        // Policies du POS & des commandes (RESTO-401..408, #6188..#6195) et
        // des sessions de table (RESTO-409, #6196) — mêmes patterns.
        Gate::policy(RestaurantPosSession::class, RestaurantPosSessionPolicy::class);
        Gate::policy(RestaurantOrder::class, RestaurantOrderPolicy::class);
        Gate::policy(RestaurantOrderPayment::class, RestaurantOrderPaymentPolicy::class);
        Gate::policy(RestaurantRefund::class, RestaurantRefundPolicy::class);
        Gate::policy(RestaurantTableSession::class, RestaurantTableSessionPolicy::class);

        // Policies stock/achats/inventaires (RESTO-501..505, #6200..#6204)
        // et réservations (RESTO-601, #6206) — mêmes patterns.
        Gate::policy(RestaurantStockLevel::class, RestaurantStockLevelPolicy::class);
        Gate::policy(RestaurantInventoryMovement::class, RestaurantInventoryMovementPolicy::class);
        Gate::policy(RestaurantPurchaseOrder::class, RestaurantPurchaseOrderPolicy::class);
        Gate::policy(RestaurantReceiving::class, RestaurantReceivingPolicy::class);
        Gate::policy(RestaurantInventoryCount::class, RestaurantInventoryCountPolicy::class);
        Gate::policy(RestaurantReservation::class, RestaurantReservationPolicy::class);
        Gate::policy(RestaurantInventoryMovement::class, RestaurantInventoryMovementPolicy::class);

        Gate::policy(RestaurantInventoryCount::class, RestaurantInventoryCountPolicy::class);

        Gate::policy(RestaurantDeliveryZone::class, RestaurantDeliveryZonePolicy::class);
    }
}
