<?php

/**
 * Routes de la verticale TravelAgency (BC-24 TRAVEL).
 *
 * Chargé depuis routes/api.php à l'intérieur du groupe /v1 — ne JAMAIS
 * re-préfixer `v1` (règle AGENTS.md).
 *
 * Middleware du groupe (convention modules, cf. crm.php) :
 *   - throttle:api     → limite globale de l'API
 *   - auth:sanctum     → authentification (Sanctum)
 *   - token.refresh    → auto-refresh du token
 *   - tenant           → résolution de la company + garde-fous statut/archive
 *   - throttle:api-plan→ limite selon le plan tarifaire
 *   - module.travelagency → feature flag companies.features.travelagency
 *
 * Référence : docs/specifications/SOLUTION_TRAVEL_AGENCY.md (§7 API v1).
 */

use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelBookingController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelCancellationPolicyController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelCarrierController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelCityController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelClassController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelCountryController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelCurrencyRateController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelHealthController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelHotelController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelLoyaltyController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelOfficeController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelPartnerController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelQuoteController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelRentalBookingController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelRentalVehicleController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelRoundTripController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelRouteController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelRouteStopController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelStationController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelTicketController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelTripController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelTripPriceController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelVehicleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan', 'module.travelagency'])
    ->prefix('travel')
    ->group(function (): void {
        // Smoke test de la verticale (TRAVEL-101/#5977) — lecture pure.
        Route::get('/ping', [TravelHealthController::class, 'ping']);

        // Référentiel géographique en lecture (TRAVEL-301/#6031).
        Route::get('/countries', [TravelCountryController::class, 'index']);
        Route::get('/cities', [TravelCityController::class, 'index']);

        // Gares/terminaux (TRAVEL-302/#6032).
        Route::get('/stations', [TravelStationController::class, 'index']);
        Route::post('/stations', [TravelStationController::class, 'store']);
        Route::get('/stations/{travelStation}', [TravelStationController::class, 'show']);
        Route::put('/stations/{travelStation}', [TravelStationController::class, 'update']);
        Route::delete('/stations/{travelStation}', [TravelStationController::class, 'destroy']);

        // Bureaux de vente (TRAVEL-303/#6033).
        Route::get('/offices', [TravelOfficeController::class, 'index']);
        Route::post('/offices', [TravelOfficeController::class, 'store']);
        Route::get('/offices/{travelOffice}', [TravelOfficeController::class, 'show']);
        Route::put('/offices/{travelOffice}', [TravelOfficeController::class, 'update']);
        Route::delete('/offices/{travelOffice}', [TravelOfficeController::class, 'destroy']);

        // Compagnies de transport (TRAVEL-304/#6034).
        Route::get('/carriers', [TravelCarrierController::class, 'index']);
        Route::post('/carriers', [TravelCarrierController::class, 'store']);
        Route::get('/carriers/{travelCarrier}', [TravelCarrierController::class, 'show']);
        Route::put('/carriers/{travelCarrier}', [TravelCarrierController::class, 'update']);
        Route::delete('/carriers/{travelCarrier}', [TravelCarrierController::class, 'destroy']);

        // Classes de service (TRAVEL-305/#6035).
        Route::get('/classes', [TravelClassController::class, 'index']);
        Route::post('/classes', [TravelClassController::class, 'store']);
        Route::get('/classes/{travelClass}', [TravelClassController::class, 'show']);
        Route::put('/classes/{travelClass}', [TravelClassController::class, 'update']);
        Route::delete('/classes/{travelClass}', [TravelClassController::class, 'destroy']);

        // Flotte propre (TRAVEL-306/#6036).
        Route::get('/vehicles', [TravelVehicleController::class, 'index']);
        Route::post('/vehicles', [TravelVehicleController::class, 'store']);
        Route::get('/vehicles/{travelVehicle}', [TravelVehicleController::class, 'show']);
        Route::put('/vehicles/{travelVehicle}', [TravelVehicleController::class, 'update']);
        Route::delete('/vehicles/{travelVehicle}', [TravelVehicleController::class, 'destroy']);

        // Routes ville→ville + étapes ordonnées (TRAVEL-307/#6037).
        Route::get('/routes', [TravelRouteController::class, 'index']);
        Route::post('/routes', [TravelRouteController::class, 'store']);
        Route::get('/routes/{travelRoute}', [TravelRouteController::class, 'show']);
        Route::put('/routes/{travelRoute}', [TravelRouteController::class, 'update']);
        Route::delete('/routes/{travelRoute}', [TravelRouteController::class, 'destroy']);
        Route::get('/routes/{travelRoute}/stops', [TravelRouteStopController::class, 'index']);
        Route::post('/routes/{travelRoute}/stops', [TravelRouteStopController::class, 'store']);
        Route::put('/routes/{travelRoute}/stops/{travelRouteStop}', [TravelRouteStopController::class, 'update']);
        Route::delete('/routes/{travelRoute}/stops/{travelRouteStop}', [TravelRouteStopController::class, 'destroy']);

        // Trajets datés + tarifs par classe (TRAVEL-308/#6038, TRAVEL-309/#6039).
        Route::get('/trips', [TravelTripController::class, 'index']);
        Route::post('/trips', [TravelTripController::class, 'store']);
        Route::get('/trips/search', [TravelTripController::class, 'search']); // TRAVEL-311/#6041 — AVANT {trip}
        Route::get('/trips/connections', [TravelTripController::class, 'connections']); // TRAVEL-809/#6099 — AVANT {trip}
        Route::get('/trips/{travelTrip}', [TravelTripController::class, 'show']);
        Route::put('/trips/{travelTrip}', [TravelTripController::class, 'update']);
        Route::delete('/trips/{travelTrip}', [TravelTripController::class, 'destroy']);
        Route::post('/trips/{travelTrip}/publish', [TravelTripController::class, 'publish']); // TRAVEL-310/#6040
        Route::post('/trips/{travelTrip}/cancel', [TravelTripController::class, 'cancel']);   // TRAVEL-310/#6040
        Route::get('/trips/{travelTrip}/prices', [TravelTripPriceController::class, 'index']);
        Route::post('/trips/{travelTrip}/prices', [TravelTripPriceController::class, 'store']);
        Route::get('/trips/{travelTrip}/prices/{travelTripPrice}', [TravelTripPriceController::class, 'show']);
        Route::put('/trips/{travelTrip}/prices/{travelTripPrice}', [TravelTripPriceController::class, 'update']);
        Route::delete('/trips/{travelTrip}/prices/{travelTripPrice}', [TravelTripPriceController::class, 'destroy']);

        // Manifeste des passagers (TRAVEL-318/#6048).
        Route::get('/trips/{travelTrip}/manifest', [TravelTripController::class, 'manifest']);

        // Réservations & billetterie (TRAVEL-312..316/#6042..#6046).
        Route::get('/bookings', [TravelBookingController::class, 'index']);
        Route::post('/bookings', [TravelBookingController::class, 'store']);
        Route::get('/bookings/{travelBooking}', [TravelBookingController::class, 'show']);
        Route::post('/bookings/{travelBooking}/confirm', [TravelBookingController::class, 'confirm']);
        Route::post('/bookings/{travelBooking}/cancel', [TravelBookingController::class, 'cancel']);
        Route::post('/bookings/{travelBooking}/refund', [TravelBookingController::class, 'refund']);
        Route::post('/bookings/{travelBooking}/issue-ticket', [TravelBookingController::class, 'issueTickets']);
        Route::post('/bookings/{travelBooking}/refund-passenger', [TravelBookingController::class, 'refundPassenger']); // TRAVEL-808/#6098

        // Check-in / embarquement (TRAVEL-317/#6047).
        Route::post('/tickets/{travelTicket}/check-in', [TravelTicketController::class, 'checkIn']);

        // Locations : véhicules + images (TRAVEL-319/#6049).
        Route::get('/rental-vehicles', [TravelRentalVehicleController::class, 'index']);
        Route::post('/rental-vehicles', [TravelRentalVehicleController::class, 'store']);
        Route::get('/rental-vehicles/{travelRentalVehicle}', [TravelRentalVehicleController::class, 'show']);
        Route::put('/rental-vehicles/{travelRentalVehicle}', [TravelRentalVehicleController::class, 'update']);
        Route::delete('/rental-vehicles/{travelRentalVehicle}', [TravelRentalVehicleController::class, 'destroy']);
        Route::get('/rental-vehicles/{travelRentalVehicle}/images', [TravelRentalVehicleController::class, 'images']);
        Route::post('/rental-vehicles/{travelRentalVehicle}/images', [TravelRentalVehicleController::class, 'storeImage']);
        Route::delete('/rental-vehicles/{travelRentalVehicle}/images/{travelRentalVehicleImage}', [TravelRentalVehicleController::class, 'destroyImage']);

        // Réservations de location (TRAVEL-320/#6050).
        Route::get('/rental-bookings', [TravelRentalBookingController::class, 'index']);
        Route::post('/rental-bookings', [TravelRentalBookingController::class, 'store']);
        Route::get('/rental-bookings/{travelRentalBooking}', [TravelRentalBookingController::class, 'show']);
        Route::post('/rental-bookings/{travelRentalBooking}/cancel', [TravelRentalBookingController::class, 'cancel']);

        // Fidélité voyageur (TRAVEL-811/#6101).
        Route::get('/loyalty/{contact}', [TravelLoyaltyController::class, 'balance']);
        Route::post('/loyalty/opt-in', [TravelLoyaltyController::class, 'optIn']);
        Route::post('/loyalty/opt-out', [TravelLoyaltyController::class, 'optOut']);
        Route::post('/loyalty/{contact}/redeem', [TravelLoyaltyController::class, 'redeem']);

        // Politiques d'annulation configurables (TRAVEL-813/#6103).
        Route::get('/cancellation-policies', [TravelCancellationPolicyController::class, 'index']);
        Route::post('/cancellation-policies', [TravelCancellationPolicyController::class, 'store']);
        Route::put('/cancellation-policies/{travelCancellationPolicy}', [TravelCancellationPolicyController::class, 'update']);
        Route::delete('/cancellation-policies/{travelCancellationPolicy}', [TravelCancellationPolicyController::class, 'destroy']);

        // Clés API transporteurs (TRAVEL-807/#6086).
        Route::post('/partner-keys', [TravelPartnerController::class, 'storePartnerKey']);
        Route::delete('/partner-keys/{travelCarrierApiKey}', [TravelPartnerController::class, 'revokePartnerKey']);

        // Taux de conversion multi-devise (TRAVEL-805/#6096).
        Route::get('/currency-rates', [TravelCurrencyRateController::class, 'index']);
        Route::post('/currency-rates', [TravelCurrencyRateController::class, 'store']);
        Route::get('/currency-rates/convert', [TravelCurrencyRateController::class, 'convert']);
        Route::get('/currency-rates/{travelCurrencyRate}', [TravelCurrencyRateController::class, 'show']);
        Route::put('/currency-rates/{travelCurrencyRate}', [TravelCurrencyRateController::class, 'update']);

        // Devis & réservations de groupe (TRAVEL-803/#6094).
        Route::get('/quotes', [TravelQuoteController::class, 'index']);
        Route::post('/quotes', [TravelQuoteController::class, 'store']);
        Route::get('/quotes/{travelQuote}', [TravelQuoteController::class, 'show']);
        Route::post('/quotes/{travelQuote}/book', [TravelQuoteController::class, 'book']);

        // Allers-retours combinés (TRAVEL-802/#6093).
        Route::post('/round-trips', [TravelRoundTripController::class, 'store']);
        Route::get('/round-trips/{travelRoundTrip}', [TravelRoundTripController::class, 'show']);

        // Hôtels + chambres (TRAVEL-321/#6051).
        Route::get('/hotels', [TravelHotelController::class, 'index']);
        Route::post('/hotels', [TravelHotelController::class, 'store']);
        Route::get('/hotels/{travelHotel}', [TravelHotelController::class, 'show']);
        Route::put('/hotels/{travelHotel}', [TravelHotelController::class, 'update']);
        Route::delete('/hotels/{travelHotel}', [TravelHotelController::class, 'destroy']);
        Route::get('/hotels/{travelHotel}/rooms', [TravelHotelController::class, 'rooms']);
        Route::post('/hotels/{travelHotel}/rooms', [TravelHotelController::class, 'storeRoom']);
        Route::put('/hotels/{travelHotel}/rooms/{travelHotelRoom}', [TravelHotelController::class, 'updateRoom']);
        Route::delete('/hotels/{travelHotel}/rooms/{travelHotelRoom}', [TravelHotelController::class, 'destroyRoom']);

    });

// ── API entrante transporteurs (TRAVEL-807/#6086) — hors auth:sanctum : le
// transporteur s'authentifie par sa clé API (header X-Partner-Key), le
// middleware travel.partner pose le contexte tenant. Idempotence par clé
// externe (external_ref), lot borné (200/200).
Route::middleware(['throttle:api', 'travel.partner'])
    ->prefix('travel/partner')
    ->group(function (): void {
        Route::post('/sync', [TravelPartnerController::class, 'sync']);
    });
