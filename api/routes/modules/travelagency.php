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

use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelAdvertController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelArticleController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelBookingController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelCommentController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelEngagementController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelExportController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelCarrierController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelCityController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelClassController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelCountryController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelHealthController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelHotelController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelOfficeController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelRentalBookingController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelRentalVehicleController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelReportController;
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

        // ── Rapports & dashboard (TRAVEL-501..504/507, #6071..#6074/#6077) ─
        Route::get('/reports/sales', [TravelReportController::class, 'sales']);
        Route::get('/reports/occupancy', [TravelReportController::class, 'occupancy']);
        Route::get('/reports/revenue', [TravelReportController::class, 'revenue']);
        Route::get('/reports/cancellations', [TravelReportController::class, 'cancellations']);
        Route::get('/reports/dashboard', [TravelReportController::class, 'dashboard']);

        // ── Export CSV idempotent (TRAVEL-505/#6075) ────────────────────────
        Route::post('/reports/export', [TravelExportController::class, 'store']);
        Route::get('/reports/export/{travelExportAsset}', [TravelExportController::class, 'show']);

        // ── Contenu éditorial — articles & catégories (TRAVEL-901/#6104) ────
        // Annonces payantes — référentiels (TRAVEL-905/#6108).
        Route::get('/advert-types', [TravelAdvertController::class, 'indexAdvertTypes']);
        Route::post('/advert-types', [TravelAdvertController::class, 'storeAdvertType']);
        Route::put('/advert-types/{travelAdvertType}', [TravelAdvertController::class, 'updateAdvertType']);
        Route::delete('/advert-types/{travelAdvertType}', [TravelAdvertController::class, 'destroyAdvertType']);
        Route::get('/advert-positions', [TravelAdvertController::class, 'indexAdvertPositions']);
        Route::post('/advert-positions', [TravelAdvertController::class, 'storeAdvertPosition']);
        Route::put('/advert-positions/{travelAdvertPosition}', [TravelAdvertController::class, 'updateAdvertPosition']);
        Route::delete('/advert-positions/{travelAdvertPosition}', [TravelAdvertController::class, 'destroyAdvertPosition']);

        Route::get('/advert-prices', [TravelAdvertController::class, 'indexAdvertPrices']);
        Route::post('/advert-prices', [TravelAdvertController::class, 'storeAdvertPrice']);
        Route::put('/advert-prices/{travelAdvertPrice}', [TravelAdvertController::class, 'updateAdvertPrice']);
        Route::delete('/advert-prices/{travelAdvertPrice}', [TravelAdvertController::class, 'destroyAdvertPrice']);

        Route::get('/adverts', [TravelAdvertController::class, 'indexAdverts']);
        Route::post('/adverts', [TravelAdvertController::class, 'storeAdvert']);
        Route::get('/adverts/{travelAdvert}', [TravelAdvertController::class, 'showAdvert']);
        Route::post('/adverts/{travelAdvert}/pay', [TravelAdvertController::class, 'payAdvert']);
        Route::post('/adverts/{travelAdvert}/validate', [TravelAdvertController::class, 'validateAdvert']);
        Route::post('/adverts/{travelAdvert}/renew', [TravelAdvertController::class, 'renewAdvert']); // TRAVEL-908/#6111
        Route::delete('/adverts/{travelAdvert}', [TravelAdvertController::class, 'destroyAdvert']);



        Route::get('/articles', [TravelArticleController::class, 'index']);
        Route::post('/articles', [TravelArticleController::class, 'store']);
        Route::get('/articles/{travelArticle}', [TravelArticleController::class, 'show']);
        Route::put('/articles/{travelArticle}', [TravelArticleController::class, 'update']);
        Route::post('/articles/{travelArticle}/moderate', [TravelArticleController::class, 'moderate']);
        Route::delete('/articles/{travelArticle}', [TravelArticleController::class, 'destroy']);
        Route::get('/article-categories', [TravelArticleController::class, 'indexCategories']);
        Route::post('/article-categories', [TravelArticleController::class, 'storeCategory']);

        // ── Commentaires (TRAVEL-902/#6105) ─────────────────────────────────
        Route::get('/comments', [TravelCommentController::class, 'index']);
        Route::post('/comments', [TravelCommentController::class, 'store']);
        Route::post('/comments/{travelComment}/moderate', [TravelCommentController::class, 'moderate']);
        Route::post('/comments/{travelComment}/report', [TravelCommentController::class, 'report']);
        Route::delete('/comments/{travelComment}', [TravelCommentController::class, 'destroy']);

        // ── Engagement — likes/partages/notes (TRAVEL-903/#6106) ────────────
        Route::post('/articles/{travelArticle}/like', [TravelEngagementController::class, 'like']);
        Route::post('/articles/{travelArticle}/unlike', [TravelEngagementController::class, 'unlike']);
        Route::post('/articles/{travelArticle}/share', [TravelEngagementController::class, 'share']);
        Route::post('/articles/{travelArticle}/rate', [TravelEngagementController::class, 'rate']);
        Route::get('/articles/{travelArticle}/engagement', [TravelEngagementController::class, 'aggregates']);
    });
