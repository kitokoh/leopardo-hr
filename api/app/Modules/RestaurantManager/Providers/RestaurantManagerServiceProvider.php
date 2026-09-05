<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Providers;

use App\Contracts\Communication\CommunicationServiceInterface;
use App\Modules\RestaurantManager\Application\Consumers\KitchenOrderNotificationConsumer;
use App\Modules\RestaurantManager\Application\Consumers\ServiceOrderNotificationConsumer;
use App\Modules\RestaurantManager\Application\Observers\RestaurantOrderObserver;
use App\Modules\RestaurantManager\Application\Services\CogsCalculator;
use App\Modules\RestaurantManager\Application\Services\StockAlertService;
use App\Modules\RestaurantManager\Application\Services\StockDecrementer;
use App\Modules\RestaurantManager\Console\Commands\ActivateRestaurantManagerCommand;
use App\Modules\RestaurantManager\Console\Commands\RestaurantOutboxDispatchCommand;
use App\Modules\RestaurantManager\Console\Commands\SeedRestaurantDemoCommand;
use App\Modules\RestaurantManager\Console\Commands\StockAlertsCommand;
use App\Modules\RestaurantManager\Domain\Contracts\RestaurantBranchRepositoryInterface;
use App\Modules\RestaurantManager\Domain\Contracts\RestaurantOrderRepositoryInterface;
use App\Modules\RestaurantManager\Domain\Contracts\RestaurantPosSessionRepositoryInterface;
use App\Modules\RestaurantManager\Domain\Contracts\RestaurantReservationRepositoryInterface;
use App\Modules\RestaurantManager\Domain\Contracts\RestaurantStockLevelRepositoryInterface;
use App\Modules\RestaurantManager\Domain\Contracts\SolutionManifest;
use App\Modules\RestaurantManager\Domain\Manifests\RestaurantManagerManifest;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Infrastructure\Repositories\RestaurantBranchRepository;
use App\Modules\RestaurantManager\Infrastructure\Repositories\RestaurantOrderRepository;
use App\Modules\RestaurantManager\Infrastructure\Repositories\RestaurantPosSessionRepository;
use App\Modules\RestaurantManager\Infrastructure\Repositories\RestaurantReservationRepository;
use App\Modules\RestaurantManager\Infrastructure\Repositories\RestaurantStockLevelRepository;
use App\Modules\RestaurantManager\Infrastructure\Services\DeliveryApps\DeliveryAppAdapterRegistry;
use App\Modules\RestaurantManager\Infrastructure\Services\DeliveryApps\GlovoDeliveryAppAdapter;
use App\Modules\RestaurantManager\Infrastructure\Services\DeliveryApps\UberEatsDeliveryAppAdapter;
use App\Modules\RestaurantManager\Infrastructure\Services\PaymentGatewayRegistry;
use App\Modules\RestaurantManager\Infrastructure\Services\PaymentGateways\CardPaymentGateway;
use App\Modules\RestaurantManager\Infrastructure\Services\PaymentGateways\CashPaymentGateway;
use App\Modules\RestaurantManager\Infrastructure\Services\PaymentGateways\MobileMoneyPaymentGateway;
use App\Modules\RestaurantManager\Infrastructure\Services\ReceivingService;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantOutboxConsumerRegistry;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantOutboxPublisher;
use App\Modules\RestaurantManager\Infrastructure\Services\StockMovementService;
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

        // RESTO-404 (#6191) — publication outbox des événements de la
        // verticale (après commit, idempotente, payload redigé).
        $this->app->singleton(RestaurantOutboxPublisher::class);

        // RESTO-406 (#6193) — registre des passerelles de paiement
        // (cash / carte / mobile money sandbox, aucun secret en dur).
        $this->app->singleton(PaymentGatewayRegistry::class, function (): PaymentGatewayRegistry {
            $registry = new PaymentGatewayRegistry;
            $registry->register(new CashPaymentGateway);
            $registry->register(new CardPaymentGateway);
            $registry->register(new MobileMoneyPaymentGateway);

            return $registry;
        });

        // RESTO-806 (#6227) — registre des adaptateurs d'apps de livraison
        // (Uber Eats / Glovo, webhooks HMAC fail-closed).
        $this->app->singleton(DeliveryAppAdapterRegistry::class, function (): DeliveryAppAdapterRegistry {
            return new DeliveryAppAdapterRegistry([
                new UberEatsDeliveryAppAdapter,
                new GlovoDeliveryAppAdapter,
            ]);

            // Flux marketplace (webhooks secret-tenant + statut sortant) — génération
            // distincte (MarketplaceAdapter) : adapters Uber Eats / Glovo complets.
            $this->app->singleton(\App\Modules\RestaurantManager\Infrastructure\Services\DeliveryApps\DeliveryAppRegistry::class, function (): \App\Modules\RestaurantManager\Infrastructure\Services\DeliveryApps\DeliveryAppRegistry {
                $registry = new \App\Modules\RestaurantManager\Infrastructure\Services\DeliveryApps\DeliveryAppRegistry;
                $registry->register(new \App\Modules\RestaurantManager\Infrastructure\Services\DeliveryApps\UberEatsAdapter);
                $registry->register(new \App\Modules\RestaurantManager\Infrastructure\Services\DeliveryApps\GlovoAdapter);

                return $registry;
            });
        });

        // RESTO-105 (#6162) — activation tenant (flag + référentiel) ;
        // RESTO-107 (#6164) — seed de démonstration idempotent ;
        // RESTO-505 (#6204) — alerte de seuil de stock (rescan complet) ;
        // RESTO-808 (#6229) — dispatcher outbox (consommation des événements).
        $this->commands([
            ActivateRestaurantManagerCommand::class,
            SeedRestaurantDemoCommand::class,
            StockAlertsCommand::class,
            RestaurantOutboxDispatchCommand::class,
        ]);

        // RESTO-808 (#6229) — registre des consommateurs d'outbox de la
        // verticale : notifications cuisine (nouvelle commande) et service
        // (commande prête) via CommunicationService (BC-13).
        $this->app->singleton(RestaurantOutboxConsumerRegistry::class, function (): RestaurantOutboxConsumerRegistry {
            $registry = new RestaurantOutboxConsumerRegistry;
            $registry->register(new KitchenOrderNotificationConsumer(app(CommunicationServiceInterface::class)));
            $registry->register(new ServiceOrderNotificationConsumer(app(CommunicationServiceInterface::class)));

            return $registry;
        });

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
        // RESTO-808 (#6229) — observateur de commande : émet
        // `restaurant.order.ready.v1` quand une commande passe à ready
        // (notifications équipe de service, découplé du flux POS).
        RestaurantOrder::observe(RestaurantOrderObserver::class);
        // Policies du référentiel branches/zones/tables (RESTO-301, #6182) :
        // enregistrement explicite des modèles métier vers leurs policies,
        // même pattern que TravelAgencyServiceProvider::boot().
        // Policies du référentiel catalogue/recettes + matières/fiscalité
        // (RESTO-302/303, #6183/#6184) — même pattern d'enregistrement.
        // Policies du référentiel menus/items/horaires (RESTO-304, #6185) et
        // fournisseurs (RESTO-305, #6186) — même pattern d'enregistrement.
        // Policies du POS & des commandes (RESTO-401..408, #6188..#6195) et
        // des sessions de table (RESTO-409, #6196) — mêmes patterns.
        // Policies stock/achats/inventaires (RESTO-501..505, #6200..#6204)
        // et réservations (RESTO-601, #6206) — mêmes patterns.
    }
}
