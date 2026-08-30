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
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelCancellationPolicyController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelCarrierController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelCityController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelClassController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelCommentController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelContactController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelCountryController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelEngagementController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelHealthController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelHotelController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelLoyaltyController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelOfficeController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelPaymentController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelQuizController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelRentalBookingController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelRentalVehicleController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelReportController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelRouteController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelRouteStopController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelShopController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelStationController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelTicketController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelTouristSiteController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelCorporateController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelCurrencyController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelConnectionController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelTripController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelTripPriceController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelVehicleController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelWebhookSubscriptionController;
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

        // Billets PDF (TRAVEL-412/413/#6064/#6065).
        Route::get('/tickets/{travelTicket}/pdf', [TravelTicketController::class, 'pdf']);
        Route::post('/tickets/{travelTicket}/revoke', [TravelTicketController::class, 'revoke']);

        // Formulaire de contact → lead CRM (TRAVEL-416/#6068).
        Route::post('/contact', [TravelContactController::class, 'store']);

        // Rapports & exports (TRAVEL-501..507/#6071..#6077) — travel.reports.
        Route::get('/reports/sales', [TravelReportController::class, 'sales']);
        Route::get('/reports/occupancy', [TravelReportController::class, 'occupancy']);
        Route::get('/reports/revenue', [TravelReportController::class, 'revenue']);
        Route::get('/reports/cancellations', [TravelReportController::class, 'cancellations']);
        Route::get('/reports/dashboard', [TravelReportController::class, 'dashboard']);
        Route::get('/reports/export', [TravelReportController::class, 'export']);

        // Abonnements webhooks transporteurs (TRAVEL-806/#6097) — travel.manage.
        Route::get('/webhook-subscriptions', [TravelWebhookSubscriptionController::class, 'index']);
        Route::post('/webhook-subscriptions', [TravelWebhookSubscriptionController::class, 'store']);
        Route::delete('/webhook-subscriptions/{subscription}', [TravelWebhookSubscriptionController::class, 'destroy']);

        // Politiques d'annulation configurables (TRAVEL-813/#6103) — travel.manage.
        Route::get('/cancellation-policies', [TravelCancellationPolicyController::class, 'index']);
        Route::post('/cancellation-policies', [TravelCancellationPolicyController::class, 'store']);
        Route::get('/cancellation-policies/{travelCancellationPolicy}', [TravelCancellationPolicyController::class, 'show']);
        Route::put('/cancellation-policies/{travelCancellationPolicy}', [TravelCancellationPolicyController::class, 'update']);
        Route::delete('/cancellation-policies/{travelCancellationPolicy}', [TravelCancellationPolicyController::class, 'destroy']);

        // Fidélité voyageur (TRAVEL-811/#6101) — opt-in RGPD, solde, récompenses.
        Route::get('/loyalty/account', [TravelLoyaltyController::class, 'account']);
        Route::get('/loyalty/entries', [TravelLoyaltyController::class, 'entries']);
        Route::post('/loyalty/opt-in', [TravelLoyaltyController::class, 'optIn']);
        Route::post('/loyalty/opt-out', [TravelLoyaltyController::class, 'optOut']);
        Route::post('/loyalty/redeem', [TravelLoyaltyController::class, 'redeem']);
        Route::get('/loyalty/rewards', [TravelLoyaltyController::class, 'rewards']);
        Route::post('/loyalty/rewards', [TravelLoyaltyController::class, 'storeReward']);

        // Correspondances multi-trajets (TRAVEL-809/#6099).
        Route::get('/shop/connections', [TravelConnectionController::class, 'search']);
        Route::post('/shop/connections/book', [TravelConnectionController::class, 'book']);

        // Boutique en ligne (TRAVEL-401..404/#6053..#6056).
        Route::get('/shop/trips', [TravelShopController::class, 'search']);
        Route::get('/shop/trips/{travelTrip}', [TravelShopController::class, 'show']);
        Route::post('/shop/bookings', [TravelShopController::class, 'storeBooking']);
        Route::get('/shop/bookings/{reference}', [TravelShopController::class, 'track']);

        // Paiements (TRAVEL-408..411/#6060..#6063).
        Route::post('/payments/initiate', [TravelPaymentController::class, 'initiate']);
        Route::get('/payments/{travelPayment}', [TravelPaymentController::class, 'show']);
        Route::post('/payments/{travelPayment}/verify', [TravelPaymentController::class, 'verify']);
        Route::post('/payments/{travelPayment}/refund', [TravelPaymentController::class, 'refund']);

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
        // Multi-devise (TRAVEL-805/#6096) — taux par période + conversion.
        Route::get('/currency-rates', [TravelCurrencyController::class, 'index']);
        Route::post('/currency-rates', [TravelCurrencyController::class, 'store']);
        Route::get('/currency-rates/convert', [TravelCurrencyController::class, 'convert']);

        // Réservations corporate (TRAVEL-803/#6094) — travel.manage.
        Route::get('/corporate-accounts', [TravelCorporateController::class, 'indexAccounts']);
        Route::post('/corporate-accounts', [TravelCorporateController::class, 'storeAccount']);
        Route::put('/corporate-accounts/{account}', [TravelCorporateController::class, 'updateAccount']);
        Route::get('/corporate-quotes', [TravelCorporateController::class, 'indexQuotes']);
        Route::post('/corporate-quotes', [TravelCorporateController::class, 'storeQuote']);
        Route::post('/corporate-quotes/{quote}/accept', [TravelCorporateController::class, 'acceptQuote']);
        Route::post('/corporate-quotes/{quote}/cancel', [TravelCorporateController::class, 'cancelQuote']);

        // Contenu communautaire (TRAVEL-901..909, issues #6104..#6112).
        Route::prefix('community')->group(function (): void {
            // Catégories d'articles (TRAVEL-901/#6104).
            Route::get('/categories', [TravelArticleController::class, 'indexCategories']);
            Route::post('/categories', [TravelArticleController::class, 'storeCategory']);
            Route::put('/categories/{category}', [TravelArticleController::class, 'updateCategory']);
            Route::delete('/categories/{category}', [TravelArticleController::class, 'destroyCategory']);

            // Articles (TRAVEL-901/#6104) — CRUD + publication + modération.
            Route::get('/articles', [TravelArticleController::class, 'index']);
            Route::post('/articles', [TravelArticleController::class, 'store']);
            Route::get('/articles/{article}', [TravelArticleController::class, 'show']);
            Route::put('/articles/{article}', [TravelArticleController::class, 'update']);
            Route::delete('/articles/{article}', [TravelArticleController::class, 'destroy']);
            Route::post('/articles/{article}/publish', [TravelArticleController::class, 'publish']);
            Route::post('/articles/{article}/moderate', [TravelArticleController::class, 'moderate']);

            // Commentaires (TRAVEL-902/#6105).
            Route::get('/articles/{article}/comments', [TravelCommentController::class, 'index']);
            Route::post('/articles/{article}/comments', [TravelCommentController::class, 'store']);
            Route::delete('/comments/{comment}', [TravelCommentController::class, 'destroy']);
            Route::post('/comments/{comment}/approve', [TravelCommentController::class, 'approve']);
            Route::post('/comments/{comment}/reject', [TravelCommentController::class, 'reject']);
            Route::post('/comments/{comment}/report', [TravelCommentController::class, 'report']);

            // Likes / partages / notes (TRAVEL-903/#6106).
            Route::post('/articles/{article}/like', [TravelEngagementController::class, 'like']);
            Route::post('/articles/{article}/unlike', [TravelEngagementController::class, 'unlike']);
            Route::post('/articles/{article}/share', [TravelEngagementController::class, 'share']);
            Route::post('/articles/{article}/rate', [TravelEngagementController::class, 'rate']);
            Route::get('/articles/{article}/engagement', [TravelEngagementController::class, 'summary']);

            // Quiz & jeu-concours (TRAVEL-904/#6107).
            Route::get('/quizzes', [TravelQuizController::class, 'index']);
            Route::post('/quizzes', [TravelQuizController::class, 'store']);
            Route::get('/quizzes/{quiz}', [TravelQuizController::class, 'show']);
            Route::post('/quizzes/{quiz}/publish', [TravelQuizController::class, 'publish']);
            Route::post('/quizzes/{quiz}/questions', [TravelQuizController::class, 'storeQuestion']);
            Route::delete('/quiz-questions/{question}', [TravelQuizController::class, 'destroyQuestion']);
            Route::post('/quizzes/{quiz}/participate', [TravelQuizController::class, 'participate']);
            Route::get('/quizzes/{quiz}/results', [TravelQuizController::class, 'results']);

            // Annonces — référentiels (TRAVEL-905/#6108).
            Route::get('/advert-types', [TravelAdvertController::class, 'indexTypes']);
            Route::post('/advert-types', [TravelAdvertController::class, 'storeType']);
            Route::get('/advert-positions', [TravelAdvertController::class, 'indexPositions']);
            Route::post('/advert-positions', [TravelAdvertController::class, 'storePosition']);

            // Annonces — tarifs (TRAVEL-906/#6109).
            Route::get('/advert-prices', [TravelAdvertController::class, 'indexPrices']);
            Route::post('/advert-prices', [TravelAdvertController::class, 'storePrice']);

            // Annonces — cycle de vie (TRAVEL-907/908/#6110/#6111).
            Route::get('/adverts', [TravelAdvertController::class, 'indexVisible']);
            Route::get('/adverts/manage', [TravelAdvertController::class, 'indexManage']);
            Route::post('/adverts', [TravelAdvertController::class, 'submit']);
            Route::post('/adverts/{advert}/pay', [TravelAdvertController::class, 'pay']);
            Route::post('/adverts/{advert}/validate', [TravelAdvertController::class, 'validateAd']);
            Route::post('/adverts/{advert}/renew', [TravelAdvertController::class, 'renew']);

            // Sites touristiques (TRAVEL-909/#6112).
            Route::get('/tourist-sites', [TravelTouristSiteController::class, 'index']);
            Route::get('/tourist-sites/search', [TravelTouristSiteController::class, 'search']);
            Route::post('/tourist-sites', [TravelTouristSiteController::class, 'store']);
            Route::put('/tourist-sites/{site}', [TravelTouristSiteController::class, 'update']);
            Route::delete('/tourist-sites/{site}', [TravelTouristSiteController::class, 'destroy']);
        });
    });
