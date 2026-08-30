<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Providers;

use App\Core\Auth\Domain\Models\Employee;
use App\Console\Commands\TravelExpireAdvertsCommand;
use App\Console\Commands\TravelOutboxDispatchCommand;
use App\Modules\TravelAgency\Console\Commands\RecalculateTravelReadModelsCommand;
use App\Modules\TravelAgency\Domain\Contracts\SolutionManifest;
use App\Modules\TravelAgency\Domain\Manifests\TravelAgencyManifest;
use App\Modules\TravelAgency\Domain\Contracts\TravelOutboxConsumer;
use App\Modules\TravelAgency\Domain\Models\TravelAdvert;
use App\Modules\TravelAgency\Domain\Models\TravelAdvertPosition;
use App\Modules\TravelAgency\Domain\Models\TravelAdvertPrice;
use App\Modules\TravelAgency\Domain\Models\TravelAdvertType;
use App\Modules\TravelAgency\Domain\Models\TravelArticle;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelComment;
use App\Modules\TravelAgency\Domain\Models\TravelCarrier;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelHotel;
use App\Modules\TravelAgency\Domain\Models\TravelOffice;
use App\Modules\TravelAgency\Domain\Models\TravelRentalBooking;
use App\Modules\TravelAgency\Domain\Models\TravelRentalVehicle;
use App\Modules\TravelAgency\Domain\Models\TravelRoute;
use App\Modules\TravelAgency\Domain\Models\TravelStation;
use App\Modules\TravelAgency\Domain\Models\TravelTicket;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelVehicle;
use App\Modules\TravelAgency\Infrastructure\Services\TravelEventPublisherConsumer;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxConsumerRegistry;
use App\Modules\TravelAgency\Policies\TravelAdvertPolicy;
use App\Modules\TravelAgency\Policies\TravelArticlePolicy;
use App\Modules\TravelAgency\Policies\TravelBookingPolicy;
use App\Modules\TravelAgency\Policies\TravelCommentPolicy;
use App\Modules\TravelAgency\Policies\TravelReportPolicy;
use App\Modules\TravelAgency\Policies\TravelCarrierPolicy;
use App\Modules\TravelAgency\Policies\TravelClassPolicy;
use App\Modules\TravelAgency\Policies\TravelHotelPolicy;
use App\Modules\TravelAgency\Policies\TravelOfficePolicy;
use App\Modules\TravelAgency\Policies\TravelRentalBookingPolicy;
use App\Modules\TravelAgency\Policies\TravelRentalVehiclePolicy;
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
 * implémentations) ; les Policies métier seront enregistrées dans `boot()`
 * au fil des lots API (épic 3xx).
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
        // TRAVEL-414 (#6066) — consommation de l'outbox événementielle.
        $this->commands([
            RecalculateTravelReadModelsCommand::class,
            TravelOutboxDispatchCommand::class,
            TravelExpireAdvertsCommand::class,
        ]);

        $this->app->singleton(TravelOutboxConsumerRegistry::class);
    }

    public function boot(): void
    {
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

        // Annonces payantes (TRAVEL-905..908, #6108..#6111).
        Gate::policy(TravelAdvertType::class, TravelAdvertPolicy::class);
        Gate::policy(TravelAdvertPosition::class, TravelAdvertPolicy::class);
        Gate::policy(TravelAdvertPrice::class, TravelAdvertPolicy::class);
        Gate::policy(TravelAdvert::class, TravelAdvertPolicy::class);

        // TRAVEL-414 (#6066) — publication des événements travel.*.v1 sur le
        // bus tenant-scopé (BC consommateurs sans import inter-modules).
        $this->app->booted(function (): void {
            /** @var TravelOutboxConsumerRegistry $registry */
            $registry = $this->app->make(TravelOutboxConsumerRegistry::class);
            /** @var TravelOutboxConsumer $consumer */
            $consumer = $this->app->make(TravelEventPublisherConsumer::class);
            $registry->register($consumer);
        });
        Gate::policy(TravelComment::class, TravelCommentPolicy::class);
        Gate::define('travel.reports', fn (Employee $actor): bool => TravelReportPolicy::authorize($actor));
    }
}
