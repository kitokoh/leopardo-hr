<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Providers;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Console\Commands\RecalculateTravelReadModelsCommand;
use App\Modules\TravelAgency\Console\Commands\TravelOutboxDispatchCommand;
use App\Modules\TravelAgency\Console\Commands\TravelSalesSettleCommand;
use App\Modules\TravelAgency\Domain\Contracts\SolutionManifest;
use App\Modules\TravelAgency\Domain\Contracts\TravelCustomerContactResolver;
use App\Modules\TravelAgency\Domain\Manifests\TravelAgencyManifest;
use App\Modules\TravelAgency\Domain\Models\TravelArticle;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelCarrier;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelComment;
use App\Modules\TravelAgency\Domain\Models\TravelHotel;
use App\Modules\TravelAgency\Domain\Models\TravelOffice;
use App\Modules\TravelAgency\Domain\Models\TravelRentalBooking;
use App\Modules\TravelAgency\Domain\Models\TravelRentalVehicle;
use App\Modules\TravelAgency\Domain\Models\TravelRoute;
use App\Modules\TravelAgency\Domain\Models\TravelStation;
use App\Modules\TravelAgency\Domain\Models\TravelTicket;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelVehicle;
use App\Modules\TravelAgency\Infrastructure\Services\NullTravelCustomerContactResolver;
use App\Modules\TravelAgency\Infrastructure\Services\TravelNotificationConsumer;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxConsumerRegistry;
use App\Modules\TravelAgency\Policies\TravelArticlePolicy;
use App\Modules\TravelAgency\Policies\TravelBookingPolicy;
use App\Modules\TravelAgency\Policies\TravelCarrierPolicy;
use App\Modules\TravelAgency\Policies\TravelClassPolicy;
use App\Modules\TravelAgency\Policies\TravelCommentPolicy;
use App\Modules\TravelAgency\Policies\TravelHotelPolicy;
use App\Modules\TravelAgency\Policies\TravelOfficePolicy;
use App\Modules\TravelAgency\Policies\TravelRentalBookingPolicy;
use App\Modules\TravelAgency\Policies\TravelRentalVehiclePolicy;
use App\Modules\TravelAgency\Policies\TravelReportPolicy;
use App\Modules\TravelAgency\Policies\TravelRoutePolicy;
use App\Modules\TravelAgency\Policies\TravelStationPolicy;
use App\Modules\TravelAgency\Policies\TravelTicketPolicy;
use App\Modules\TravelAgency\Policies\TravelTripPolicy;
use App\Modules\TravelAgency\Policies\TravelVehiclePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Provider du module TravelAgency (BC-24 TRAVEL).
 *
 * Fondations de la verticale « Agence de Voyage » (TRAVEL-101, issue #5977) :
 * portage de l'ancien projet gv-back (vente de billets en ligne) dans
 * l'architecture DDD multi-tenant Leopardo HR.
 *
 * `register()` enregistre les ports & adapters du module (contrats →
 * implémentations) ; les Policies métier sont enregistrées dans `boot()`.
 *
 * L'activation par tenant passe par le feature flag `travelagency`
 * (companies.features) — voir EnsureTravelAgencyModuleMiddleware (TRAVEL-102)
 * et ActivateTravelAgencyAction (TRAVEL-105).
 */
class TravelAgencyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SolutionManifest::class, TravelAgencyManifest::class);

        // TRAVEL-506 (#6076) — recalcul des read models de reporting.
        // TRAVEL-414..418 (#6066..#6070) — outbox dispatch, expiration des
        // réservations pending, synthèse Accounting.
        $this->commands([
            RecalculateTravelReadModelsCommand::class,
            TravelOutboxDispatchCommand::class,
            TravelSalesSettleCommand::class,
        ]);

        // Registre des consommateurs d'outbox TravelAgency (TRAVEL-414) ;
        // le consommateur Notifications BC-13 (TRAVEL-415) s'y déclare dans
        // boot().
        $this->app->singleton(TravelOutboxConsumerRegistry::class);

        // Contrat TravelCustomerContactResolver (TRAVEL-416) : implémentation
        // par défaut vide — le BC CRM client fournira la vraie résolution.
        $this->app->bind(
            TravelCustomerContactResolver::class,
            static fn (): NullTravelCustomerContactResolver => new NullTravelCustomerContactResolver
        );
    }

    public function boot(): void
    {
        // TRAVEL-415 (#6067) — consommateur outbox → notifications voyageur.
        $registry = $this->app->make(TravelOutboxConsumerRegistry::class);
        $registry->register($this->app->make(TravelNotificationConsumer::class));

        Gate::policy(TravelStation::class, TravelStationPolicy::class);
        Gate::policy(TravelOffice::class, TravelOfficePolicy::class);
        Gate::policy(TravelCarrier::class, TravelCarrierPolicy::class);
        Gate::policy(TravelClass::class, TravelClassPolicy::class);
        Gate::policy(TravelVehicle::class, TravelVehiclePolicy::class);
        Gate::policy(TravelRoute::class, TravelRoutePolicy::class);
        Gate::policy(TravelTrip::class, TravelTripPolicy::class);
        Gate::policy(TravelBooking::class, TravelBookingPolicy::class);
        Gate::policy(TravelTicket::class, TravelTicketPolicy::class);
        Gate::policy(TravelRentalVehicle::class, TravelRentalVehiclePolicy::class);
        Gate::policy(TravelRentalBooking::class, TravelRentalBookingPolicy::class);
        Gate::policy(TravelHotel::class, TravelHotelPolicy::class);

        // Contenu éditorial (TRAVEL-901/902, #6104/#6105) + rapports
        // (TRAVEL-501..507, #6071..#6077) — ability `travel.reports`
        // ouverte aux rôles opérationnels de l'agence.
        Gate::policy(TravelArticle::class, TravelArticlePolicy::class);
        Gate::policy(TravelComment::class, TravelCommentPolicy::class);
        Gate::define('travel.reports', fn (Employee $actor): bool => TravelReportPolicy::authorize($actor));
    }
}
