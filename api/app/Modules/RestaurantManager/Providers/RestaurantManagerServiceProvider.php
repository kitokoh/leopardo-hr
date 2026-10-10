<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Providers;

use App\Contracts\Communication\CommunicationServiceInterface;
use App\Core\Solutions\Contracts\DemoDataKit;
use App\Core\Solutions\DemoDataRegistry;
use App\Core\Solutions\SolutionCatalogue;
use App\Events\SolutionActivated;
use App\Modules\RestaurantManager\Application\Actions\ActivateRestaurantManagerAction;
use App\Modules\RestaurantManager\Application\Consumers\KitchenOrderNotificationConsumer;
use App\Modules\RestaurantManager\Application\Consumers\ServiceOrderNotificationConsumer;
use App\Modules\RestaurantManager\Application\Observers\RestaurantOrderObserver;
use App\Modules\RestaurantManager\Application\Services\CogsCalculator;
use App\Modules\RestaurantManager\Application\Services\StockAlertService;
use App\Modules\RestaurantManager\Application\Services\StockDecrementer;
use App\Modules\RestaurantManager\Console\Commands\ActivateRestaurantManagerCommand;
use App\Modules\RestaurantManager\Console\Commands\RestaurantNoShowExpireCommand;
use App\Modules\RestaurantManager\Console\Commands\RestaurantOutboxDispatchCommand;
use App\Modules\RestaurantManager\Console\Commands\RestaurantReservationJobsCommand;
use App\Modules\RestaurantManager\Console\Commands\RestaurantSendRemindersCommand;
use App\Modules\RestaurantManager\Console\Commands\SeedRestaurantDemoCommand;
use App\Modules\RestaurantManager\Console\Commands\StockAlertsCommand;
use App\Modules\RestaurantManager\Domain\Contracts\RestaurantBranchRepositoryInterface;
use App\Modules\RestaurantManager\Domain\Contracts\RestaurantOrderRepositoryInterface;
use App\Modules\RestaurantManager\Domain\Contracts\RestaurantPosSessionRepositoryInterface;
use App\Modules\RestaurantManager\Domain\Contracts\RestaurantReservationRepositoryInterface;
use App\Modules\RestaurantManager\Domain\Contracts\RestaurantStockLevelRepositoryInterface;
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
use App\Modules\RestaurantManager\Infrastructure\Services\PaymentGateways\CardOnlinePaymentGateway;
use App\Modules\RestaurantManager\Infrastructure\Services\PaymentGateways\CardPaymentGateway;
use App\Modules\RestaurantManager\Infrastructure\Services\PaymentGateways\CashPaymentGateway;
use App\Modules\RestaurantManager\Infrastructure\Services\PaymentGateways\MobileMoneyPaymentGateway;
use App\Modules\RestaurantManager\Infrastructure\Services\ReceivingService;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantDemoSeederService;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantOutboxConsumerRegistry;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantOutboxPublisher;
use App\Modules\RestaurantManager\Infrastructure\Services\StockMovementService;
use Illuminate\Support\Facades\Event;
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
        // BOS-014 (#8201) — le manifest (contrat Core v2) est enregistré au
        // catalogue central des solutions (clé d'allowlist
        // `restaurantmanager`) : la verticale devient activable via
        // `SolutionActivator` avec installation des permissions déclarées
        // (BOS-013). Fin du singleton de contrat local non conforme et non
        // enregistré (anti-pattern #7220-bis).
        if (! $this->app->bound(SolutionCatalogue::class)) {
            $this->app->singleton(SolutionCatalogue::class, static fn (): SolutionCatalogue => new SolutionCatalogue);
        }

        $this->app->resolving(SolutionCatalogue::class, function (SolutionCatalogue $catalogue): void {
            $catalogue->register('restaurantmanager', static fn (): RestaurantManagerManifest => new RestaurantManagerManifest);
        });

        // #7865 — kit de données de démonstration de la verticale (code de
        // solution `restaurant`, manifest porté par Modules/Restaurant — le
        // SEEDER vit ici, RESTO-107). Pattern partagé `SolutionCatalogue` :
        // singleton avec garde `bound()` + `resolving()` (registre partagé
        // entre modules, ne jamais le ré-écraser) ; factory paresseuse — le
        // seeder n'est instancié qu'à l'import effectif.
        if (! $this->app->bound(DemoDataRegistry::class)) {
            $this->app->singleton(DemoDataRegistry::class, static fn (): DemoDataRegistry => new DemoDataRegistry);
        }

        $this->app->resolving(DemoDataRegistry::class, function (DemoDataRegistry $registry): void {
            $registry->register('restaurant', fn (): DemoDataKit => $this->app->make(RestaurantDemoSeederService::class));
        });

        // GEO-06 (#8355, BC-33 GEO) — type recherchable `restaurant` de la
        // registry opt-in `geo.searchables` : vue d'adaptation publique des
        // branches (scope « annuaire public » fail-closed). Enregistrement
        // paresseux via la façade transverse partagée — jamais d'import du
        // module Geo lui-même (garde d'isolation #5584, inversion de
        // dépendance : la verticale s'enregistre, le core ne la connaît pas).
        $this->app->resolving(\App\Shared\Contracts\Geo\GeoServiceContract::class, function (\App\Shared\Contracts\Geo\GeoServiceContract $geo): void {
            $geo->registerSearchable('restaurant', \App\Modules\RestaurantManager\Infrastructure\Geo\RestaurantGeoBranch::class);
        });

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
        // (cash / carte / mobile money, aucun secret en dur).
        // #7728 (BC-21) — les passerelles EN LIGNE (carte en ligne Stripe,
        // mobile money production) sont branchées sur les PROFILS DE PAIEMENT
        // du tenant via le contrat partagé (garde d'isolation #5584).
        $this->app->singleton(PaymentGatewayRegistry::class, function (): PaymentGatewayRegistry {
            $tenantProfiles = $this->app->make(\App\Shared\Contracts\Payments\TenantPaymentProfileResolverInterface::class);

            $registry = new PaymentGatewayRegistry;
            $registry->register(new CashPaymentGateway);
            $registry->register(new CardPaymentGateway);
            $registry->register(new CardOnlinePaymentGateway($tenantProfiles));
            $registry->register(new MobileMoneyPaymentGateway($tenantProfiles));

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
        //
        // #8004 — Laravel n'auto-découvre QUE `app/Console/Commands` : les
        // commandes du module DOIVENT être listées ici (`restaurant:no-show-expire`,
        // `restaurant:send-reminders`, `leopardo:restaurant:reservation-jobs`
        // échouaient en CommandNotFoundException). Le doublon non enregistré ni
        // testé `RestaurantStockAlertCommand` (même nom que `StockAlertsCommand`,
        // scoping tenant absent) est supprimé — `StockAlertsCommand` reste
        // l'unique implémentation de `leopardo:restaurant:stock-alerts`.
        $this->commands([
            ActivateRestaurantManagerCommand::class,
            SeedRestaurantDemoCommand::class,
            StockAlertsCommand::class,
            RestaurantOutboxDispatchCommand::class,
            RestaurantNoShowExpireCommand::class,
            RestaurantSendRemindersCommand::class,
            RestaurantReservationJobsCommand::class,
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

        // #7976 — l'activation standard d'une verticale passe par
        // `SolutionActivator` avec le code de solution `restaurant`
        // (RestaurantManifest), qui ne pose QUE le flag `restaurant` : les
        // routes gatées par `module.restaurantmanager` restaient donc en 403.
        // Même pattern d'isolation que TravelAgency : le module écoute
        // `SolutionActivated` et pose son flag opérationnel + amorce son
        // référentiel via `ActivateRestaurantManagerAction` (idempotent :
        // setFeature + insertOrIgnore), sans couplage core → module.
        // BOS-014 (#8201) — le code `restaurantmanager` est désormais AUSSI
        // enregistré au catalogue (activation directe possible, ex. console
        // plateforme) : le listener couvre les DEUX codes, la même cascade
        // idempotente s'applique dans les deux sens.
        Event::listen(SolutionActivated::class, static function (SolutionActivated $event): void {
            if ($event->solution !== 'restaurant' && $event->solution !== 'restaurantmanager') {
                return;
            }

            app(ActivateRestaurantManagerAction::class)->execute($event->company);

            // BOS-013/014 — l'activation par le code DESCRIPTEUR `restaurant`
            // pose le flag opérationnel via la cascade ci-dessus, SANS passer
            // par `SolutionActivator::activate('restaurantmanager')` : les
            // grants des permissions opérationnelles (restaurant.manage, …)
            // ne seraient jamais installés. Installation idempotente ici —
            // inutile quand l'événement vient de `restaurantmanager`
            // (l'activateur l'a déjà fait dans sa transaction).
            if ($event->solution === 'restaurant') {
                app(\App\Core\Solutions\SolutionActivator::class)->installPermissionsFor($event->company, 'restaurantmanager');
            }
        });

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
