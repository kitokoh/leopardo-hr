<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Providers;

use App\Modules\TravelAgency\Domain\Contracts\SolutionManifest;
use App\Modules\TravelAgency\Domain\Manifests\TravelAgencyManifest;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelCarrier;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelHotel;
use App\Modules\TravelAgency\Domain\Models\TravelOffice;
use App\Modules\TravelAgency\Domain\Models\TravelRentalBooking;
use App\Modules\TravelAgency\Domain\Models\TravelRentalVehicle;
use App\Modules\TravelAgency\Domain\Models\TravelRoundTrip;
use App\Modules\TravelAgency\Domain\Models\TravelRoute;
use App\Modules\TravelAgency\Domain\Models\TravelStation;
use App\Modules\TravelAgency\Domain\Models\TravelTicket;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelVehicle;
use App\Modules\TravelAgency\Policies\TravelBookingPolicy;
use App\Modules\TravelAgency\Policies\TravelCarrierPolicy;
use App\Modules\TravelAgency\Policies\TravelClassPolicy;
use App\Modules\TravelAgency\Policies\TravelHotelPolicy;
use App\Modules\TravelAgency\Policies\TravelOfficePolicy;
use App\Modules\TravelAgency\Policies\TravelRentalBookingPolicy;
use App\Modules\TravelAgency\Policies\TravelRentalVehiclePolicy;
use App\Modules\TravelAgency\Policies\TravelRoundTripPolicy;
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
        Gate::policy(TravelRoundTrip::class, TravelRoundTripPolicy::class);
    }
}
