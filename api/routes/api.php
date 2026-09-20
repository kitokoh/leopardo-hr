<?php

use App\AI\Interfaces\Api\V1\Controllers\VoiceController;
use App\Core\Auth\Interfaces\Api\V1\Controllers\AuthController;
use App\Core\Auth\Interfaces\Api\V1\Controllers\LoginCodeController;
use App\Core\Auth\Interfaces\Api\V1\Controllers\PasswordResetController;
use App\Core\Auth\Interfaces\Api\V1\Controllers\PlatformAuthController;
use App\Core\Auth\Interfaces\Api\V1\Controllers\TwoFactorAuthController;
use App\Core\Feature\Interfaces\Api\V1\Controllers\FeatureManifestController;
use App\Http\Controllers\Web\PlatformCompanyController;
use App\Modules\Accounting\Interfaces\Api\V1\Controllers\AccountingPaymentWebhookController;
use App\Modules\Attendance\Interfaces\Api\V1\Controllers\BiometricEnrollmentController;
use App\Modules\Billing\Interfaces\Api\V1\Controllers\CompanyRequestController;
use App\Modules\Billing\Interfaces\Api\V1\Controllers\PaymentWebhookController;
use App\Modules\Billing\Interfaces\Api\V1\Controllers\PlatformCompanySubscriptionController;
use App\Modules\Billing\Interfaces\Api\V1\Controllers\PlatformPaymentGatewayController;
use App\Modules\Billing\Interfaces\Api\V1\Controllers\PlatformPlanAdminController;
use App\Modules\Billing\Interfaces\Api\V1\Controllers\PlatformPlanController;
use App\Modules\Billing\Interfaces\Api\V1\Controllers\SelfServiceTrialController;
use App\Modules\Billing\Interfaces\Api\V1\Controllers\StripeWebhookController;
use App\Modules\EdgeSync\Interfaces\Api\V1\Controllers\EdgeNodeController;
use App\Modules\HR\Interfaces\Api\V1\Controllers\CompanyBankingController;
use App\Modules\HR\Interfaces\Api\V1\Controllers\CompanyBrandingController;
use App\Modules\HR\Interfaces\Api\V1\Controllers\CompanyModuleController;
use App\Modules\HR\Interfaces\Api\V1\Controllers\PrivacyController;
use App\Modules\Marketing\Interfaces\Api\V1\Controllers\AcquisitionFunnelEventController;
use App\Modules\Marketing\Interfaces\Api\V1\Controllers\MarketingLeadController;
use App\Modules\Notification\Interfaces\Api\V1\Controllers\EmailBounceWebhookController;
use App\Modules\Notification\Interfaces\Api\V1\Controllers\NotificationPreferenceController;
use App\Modules\Onboarding\Interfaces\Api\V1\Controllers\DemoDataController;
use App\Modules\Onboarding\Interfaces\Api\V1\Controllers\OnboardingChecklistController;
use App\Modules\Onboarding\Interfaces\Api\V1\Controllers\OnboardingController;
use App\Modules\Onboarding\Interfaces\Api\V1\Controllers\SetupInterviewController;
use App\Modules\Onboarding\Interfaces\Api\V1\Controllers\WelcomeScreenController;
use App\Modules\Payroll\Interfaces\Api\V1\Controllers\IslamicCalendarController;
use App\Modules\Payroll\Interfaces\Api\V1\Controllers\PayrollAuditController;
use App\Modules\Payroll\Interfaces\Api\V1\Controllers\PayrollSimulationController;
use App\Modules\Payroll\Interfaces\Api\V1\Controllers\PublicHolidayController;
use App\Modules\Payroll\Interfaces\Api\V1\Controllers\RateValidationAdminController;
use App\Modules\Payroll\Interfaces\Api\V1\Controllers\SocialContributionAdminController;
use App\Modules\Payroll\Interfaces\Api\V1\Controllers\TaxSlabAdminController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\ClientEventController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\CommunicationAnalyticsController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\DemoUserController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\HealthController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\LaunchReadinessController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\MetricsController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformAcquisitionFunnelController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformAdminAiConversationController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformAdminDashboardController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformAdminFleetAlertController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformAdminTrainingController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformAdminWebhookController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformAiMonitoringController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformAiSettingsController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformAnnouncementController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformCompanyDeletionController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformCompanyFeatureController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformCompanyHealthController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformCompanyRequestController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformCountryDefaultsController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformCrmPipelineController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformEmailTemplateController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformFeatureKillSwitchController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformHrReportController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformImpersonationController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformMarketingLeadController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformMarketingOAuthConfigController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformMetricsOverviewController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformNotificationObservabilityController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformSolutionSurveyStatsController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformSupportTicketController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformTeamController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformUserController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformUsersController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\QueueObservabilityController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\SupportedCountryController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\SupportTicketController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\TranslationCatalogController;
use App\Modules\Recruitment\Interfaces\Api\V1\Controllers\CandidateApplicationController;
use App\Modules\Recruitment\Interfaces\Api\V1\Controllers\PublicCareerController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantDeliveryAppWebhookController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantKioskController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantPaymentCallbackController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantPublicShopController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelCarrierSyncController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelCustomerAccountController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelMarketplaceController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelPaymentController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelPublicShopController;
use Illuminate\Support\Facades\Route;

// Edge routes are now registered by EdgeSyncServiceProvider

// #7666 — alias de supervision NON versionné : les sondes externes
// (UptimeRobot, Better Uptime, intégrateurs qui testent l'API à la main)
// essaient d'abord `/api/health` par convention — vérifié 404 en prod le
// 2026-09-19, elles concluaient « API morte » alors qu'elle était up.
// `/api/v1/health` reste la route canonique (Render `healthCheckPath`,
// docs/ops/HEALTH_ENDPOINTS.md) ; cet alias sert la MÊME sonde, même
// throttle. `ApiVersionMiddleware` l'accepte (segment 2 non `v\d+` →
// version courante v1).
Route::get('/health', HealthController::class)->middleware('throttle:60,1');

Route::prefix('v1')->group(function (): void {
    // Sonde live+ready : DB + Redis + storage. Consommee par Render (deploy hook)
    // et la supervision externe. 503 si la DB tombe, 200 sinon (Redis et storage
    // peuvent etre degrades sans bloquer l'API).
    Route::get('/health', HealthController::class)->middleware('throttle:60,1');
    Route::get('/health/live', [HealthController::class, 'live'])->middleware('throttle:60,1');
    Route::get('/health/ready', [HealthController::class, 'ready'])->middleware('throttle:60,1');
    // Platform-wide metrics (versions PHP/Laravel, drivers, tenant/employee
    // counts) are business intelligence + version fingerprinting material:
    // they must not be served anonymously. See issue #1466.
    Route::get('/metrics', MetricsController::class)
        ->middleware(['auth:super_admin_api', 'throttle:metrics']);

    // Issue #5616 — Serve TTS audio via URL signée temporaire (sans auth Sanctum).
    // La route est protégée par la signature Laravel (hasValidRelativeSignature),
    // TTL = VoiceController::TTS_URL_TTL_SECONDS (60 s).
    Route::get('/voice/tts/{filename}', [VoiceController::class, 'serveTts'])
        ->name('tts.serve')
        ->middleware('throttle:60,1');

    // Auth (core, hors module)
    Route::middleware(['throttle:auth-sensitive'])->group(function (): void {
        Route::post('/auth/login', [AuthController::class, 'login']);
        Route::post('/auth/register', [AuthController::class, 'register']);
        // #5436 : vérification du challenge 2FA (public, bucket auth-sensitive).
        Route::post('/auth/2fa/verify', [TwoFactorAuthController::class, 'verify']);
        // Issue #2626 : réinitialisation de mot de passe (usage unique, 60 min).
        Route::post('/auth/forgot-password', [PasswordResetController::class, 'forgot']);
        Route::post('/auth/reset-password', [PasswordResetController::class, 'reset']);
        // #7490 : connexion par code à usage unique pour les comptes SANS mot
        // de passe défini (self-service). Réponse générique côté demande
        // (anti-énumération), verrou applicatif à 5 échecs côté verify — le
        // bucket auth-sensitive (email+IP) suit la même politique que /auth/login.
        Route::post('/auth/login-code/request', [LoginCodeController::class, 'request']);
        Route::post('/auth/login-code/verify', [LoginCodeController::class, 'verify']);
        Route::post('/auth/google/token', [AuthController::class, 'handleGoogleToken']);

        // QA onboarding 2026-09-14 — le flux Google a besoin d'une SESSION.
        // Le state anti-CSRF (#2619) et l'intention de parcours
        // (`google_oauth_intent` / `google_oauth_plan`) vivent dans la session,
        // et le callback de la vitrine relaie le cookie
        // (front/web/src/app/api/v1/auth/google/callback/route.ts). Or le groupe
        // `api` ne comporte AUCUN middleware de session : mesuré en dev ET en
        // prod, `GET /auth/google` échouait en
        // `auth.google.redirect_failed { "Session store not set on request." }`
        // → 503 GOOGLE_OAUTH_UNAVAILABLE. Le flux était donc structurellement
        // inutilisable, même clés configurées.
        //
        // On n'ajoute la session QU'À ces deux routes : l'imposer à tout le
        // groupe `api` activerait la protection CSRF Sanctum sur les routes
        // utilisées par la SPA admin et les clients mobiles.
        Route::middleware([
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            'throttle:auth-sensitive',
        ])->group(function (): void {
            Route::get('/auth/google', [AuthController::class, 'redirectToGoogle']);
            Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);
        });
        Route::post('/platform/auth/login', [PlatformAuthController::class, 'login']);
    });

    // Issue #4501 : le catalogue i18n pré-login ne doit PAS partager le bucket
    // auth-sensitive (10/min/IP) avec le login — des échecs de login depuis
    // la même IP (NAT) affameraient les traductions de la UI et vice-versa.
    // Bucket public-registry dédié (60/min/IP), largement suffisant avec les
    // ETag/304 du contrôleur.
    Route::middleware(['throttle:public-registry'])->group(function (): void {
        Route::get('/i18n/catalog', [TranslationCatalogController::class, 'index']);
        Route::get('/i18n/catalog/{locale}', [TranslationCatalogController::class, 'show']);
    });

    // Demo users (public, disabled by default; opt-in only via DEMO_MODE_ENABLED=true
    // on staging/demo environments — DemoUserController::index() enforces the same
    // gate and 404s when the flag is off, see docs/security/AUDIT_API_2026-07-19.md)
    Route::middleware(['throttle:10,1'])->get('/demo-users', [DemoUserController::class, 'index']);

    // Module 6 — Public Onboarding (sans auth, throttle strict)
    Route::middleware(['throttle:10,1'])->prefix('onboarding')->group(function (): void {
        Route::get('/invitation/{token}', [OnboardingController::class, 'show']);
        Route::post('/invitation/{token}/activate', [OnboardingController::class, 'activate']);
    });

    // Self-service trial provisioning (public, throttle strict)
    // #7609 — l'inscription garde sa garde anti-spam 5/15 min.
    Route::middleware(['throttle:5,15'])->group(function (): void {
        Route::post('/trial/signup', [SelfServiceTrialController::class, 'signup']);
    });

    // #7609 — /trial/verify a son PROPRE seau : avec le seau partagé, la 5e
    // vérification recevait le 429 du throttle (`TOO_MANY_REQUESTS`) AVANT le
    // verrou applicatif (5 échecs → `otp_locked_until`), qui devenait donc
    // inatteignable côté utilisateur — mesuré avant correctif. Depuis le
    // contrôleur, un compte verrouillé doit AUSSI pouvoir définir son mot de
    // passe : `set-password` suit le même seau dédié.
    Route::middleware(['throttle:trial-verify'])->group(function (): void {
        Route::post('/trial/verify', [SelfServiceTrialController::class, 'verify']);
        // Onboarding sans mailer : le prospect définit lui-même son mot de passe
        // avec le provisioning_token qu'il détient déjà (voir setPassword()).
        Route::post('/trial/set-password', [SelfServiceTrialController::class, 'setPassword']);
    });

    // Issue #2621 : GET /trial/status est POLLÉ par la vitrine (~1 req/5 s)
    // — limit dédié 60/min (hors throttle:5,15 qui 429erait le polling).
    Route::middleware(['throttle:trial-status'])->get('/trial/status', [SelfServiceTrialController::class, 'status']);

    // PA2-MKT-007 - Public vitrine lead capture (signup/demo/contact/
    // newsletter), called server-to-server from front/web's Next.js API
    // routes right after captureMarketingLead() logs + forwards the lead.
    // Protected by a shared secret (see services.marketing_lead_webhook),
    // not Sanctum, since the caller has no tenant yet.
    Route::middleware(['throttle:webhooks-inbound'])->post('/marketing/leads', [MarketingLeadController::class, 'store']);

    // #7496 — événements d'étape du funnel d'acquisition (vitrine, server-to-
    // server via la route Next /api/forms/funnel-event, même secret partagé
    // que /marketing/leads). Aucune PII : liste fermée d'étapes + corrélation.
    Route::middleware(['throttle:webhooks-inbound'])->post('/funnel/events', [AcquisitionFunnelEventController::class, 'store']);

    // Stripe/Chargily webhooks (public, verified by provider signature inside
    // the controller). PA2-API-005: dedicated 'webhooks-inbound' throttle since
    // these routes sit outside the authenticated 'api' middleware group below.
    // TRAVEL-1001 (#6114) — boutique publique (jeton tenant signé, sans
    // auth utilisateur) — throttling renforcé `shop-public`.
    Route::middleware(['throttle:shop-public', 'travel.public.shop'])->group(function (): void {
        Route::get('/public/travel/shop/trips', [TravelPublicShopController::class, 'search']);
        Route::get('/public/travel/shop/trips/{travelTrip}', [TravelPublicShopController::class, 'show']);
        Route::post('/public/travel/shop/bookings', [TravelPublicShopController::class, 'storeBooking']);
        Route::get('/public/travel/shop/bookings/{reference}', [TravelPublicShopController::class, 'track']);
        // #7395 — annulation en ligne de l'espace voyageur (référence + code
        // de validation du billet, sans jeton boutique ni compte employé).
        Route::post('/public/travel/shop/bookings/{reference}/cancel', [TravelPublicShopController::class, 'cancel']);
        // TRAVEL-1002 (#6115) — tunnel complet : paiement en ligne + e-billet.
        Route::post('/public/travel/payments/initiate', [TravelPublicShopController::class, 'initiatePayment']);
        Route::get('/public/travel/tickets/{ticket}/pdf', [TravelPublicShopController::class, 'ticketPdf']);
        // #7737 — alias marketplace du suivi/annulation/e-billet : même
        // surface passager #7395 (référence + code de validation, tenant
        // résolu par la ressource — aucun jeton boutique requis sur ces
        // routes bornées), exposée sous le préfixe marketplace pour le
        // front `travel-web` (épic #7736).
        Route::get('/public/travel/marketplace/bookings/{reference}', [TravelPublicShopController::class, 'track']);
        Route::post('/public/travel/marketplace/bookings/{reference}/cancel', [TravelPublicShopController::class, 'cancel']);
        Route::get('/public/travel/marketplace/tickets/{ticket}/pdf', [TravelPublicShopController::class, 'ticketPdf']);
    });

    // #7737 — MARKETPLACE inter-agences (épic #7736) : recherche agrégée
    // cross-tenant des trajets publiés des agences OPT-IN (jeton boutique
    // actif + feature `travelagency`) et tunnel d'achat SANS jeton d'agence.
    // Le tenant est résolu par trajet (détail/réservation) ou par référence
    // (paiement) dans le contrôleur — throttling `shop-public` uniquement.
    Route::middleware(['throttle:shop-public'])->group(function (): void {
        Route::get('/public/travel/marketplace/cities', [TravelMarketplaceController::class, 'cities']);
        Route::get('/public/travel/marketplace/trips', [TravelMarketplaceController::class, 'search']);
        Route::get('/public/travel/marketplace/trips/{trip}', [TravelMarketplaceController::class, 'show'])->whereNumber('trip');
        Route::post('/public/travel/marketplace/bookings', [TravelMarketplaceController::class, 'storeBooking']);
        Route::post('/public/travel/marketplace/payments/initiate', [TravelMarketplaceController::class, 'initiatePayment']);
    });

    // #7739 — comptes clients GRAND PUBLIC de la marketplace (épic #7736) :
    // guard Sanctum DÉDIÉ `travel_customer` (jamais le guard employés).
    // Inscription/connexion sous `auth-sensitive` (e-mail + IP, même
    // politique que /auth/login) ; surface connectée (profil, déconnexion,
    // « mes réservations » cross-agences bornées au compte) sous
    // `shop-public` + auth du guard dédié.
    Route::middleware(['throttle:auth-sensitive'])->group(function (): void {
        Route::post('/public/travel/marketplace/account/register', [TravelCustomerAccountController::class, 'register']);
        Route::post('/public/travel/marketplace/account/login', [TravelCustomerAccountController::class, 'login']);
    });

    Route::middleware(['throttle:shop-public', 'auth:travel_customer'])->group(function (): void {
        Route::post('/public/travel/marketplace/account/logout', [TravelCustomerAccountController::class, 'logout']);
        Route::get('/public/travel/marketplace/account/me', [TravelCustomerAccountController::class, 'me']);
        Route::get('/public/travel/marketplace/account/bookings', [TravelCustomerAccountController::class, 'bookings']);
    });

    Route::middleware(['throttle:webhooks-inbound'])->group(function (): void {
        // TRAVEL-409 (#6061) — callback provider paiements TravelAgency
        // (signé HMAC, idempotent — public, vérifié dans le contrôleur).
        Route::post('/travel/payments/callback', [TravelPaymentController::class, 'callback']);
        // TRAVEL-807 (#6086) — API entrante de synchronisation des trajets
        // transporteurs (jeton X-Carrier-Token, upsert idempotent par clé
        // externe — public, authentifié dans le contrôleur).
        Route::post('/travel/carrier-sync/trips', [TravelCarrierSyncController::class, 'upsertTrip']);
        Route::post('/webhooks/stripe', StripeWebhookController::class);
        Route::post('/webhooks/chargily', [PaymentWebhookController::class, 'chargily']);
        // #5272 — webhook des paiements en ligne des documents comptables.
        // Public (signature HMAC fail-closed), tenant résolu par metadata.
        // Pas de contrainte whereIn : la passerelle inconnue est rejetée par le
        // service (401 WEBHOOK_SIGNATURE_INVALID, fail-closed).
        Route::post('/accounting/payment-webhooks/{gateway}', AccountingPaymentWebhookController::class);
        // PA2-COMM-007 - Email provider bounce/complaint notifications
        // (Postmark, SES, Mailgun, ...), protected by a shared secret header
        // instead of Sanctum since the caller is a third-party mail provider.
        Route::post('/webhooks/email-bounce', EmailBounceWebhookController::class);
        // RESTO-407 (#6194) — callback signé de confirmation mobile money de la
        // verticale RestaurantManager. Public : la confiance est portée par la
        // signature HMAC (secret par tenant, fail-closed) ; le tenant est résolu
        // depuis le payload signé puis posé via TenantManager (pattern #5272).
        Route::post('/restaurant/payments/{payment}/callback', [RestaurantPaymentCallbackController::class, 'handle']);
        // RESTO-806 (#6227) — webhooks entrants apps de livraison (Uber Eats,
        // Glovo, ...). Public : signature HMAC fail-closed par adaptateur
        // (secret par tenant), tenant résolu depuis le payload signé.
        Route::post('/restaurant/webhooks/delivery-apps/{provider}', [RestaurantDeliveryAppWebhookController::class, 'handle']);
    });

    // Public careers portal (ATS): unauthenticated job listing/detail, the
    // Google Jobs / Indeed XML feed, and candidate application submission.
    // Tenant is resolved from {companySlug}, not from Sanctum, since visitors
    // have no account. See feat(recruitment): ATS Backend - APIs Publiques.
    Route::middleware(['throttle:public-careers'])->prefix('public/careers')->group(function (): void {
        Route::get('/{companySlug}', [PublicCareerController::class, 'index']);
        Route::get('/{companySlug}/feed.xml', [PublicCareerController::class, 'feed']);
        Route::get('/{companySlug}/jobs/{jobPosting}', [PublicCareerController::class, 'show'])->whereNumber('jobPosting');
        Route::post('/{companySlug}/jobs/{jobPosting}/apply', [CandidateApplicationController::class, 'store'])->whereNumber('jobPosting');
    });

    // Issue #4217 (audit 360° 2026-08-16) — registre multi-pays canonique
    // (#1867) accessible SANS auth : la vitrine, l'onboarding public et les
    // apps mobiles pré-login listent les pays supportés avant toute connexion.
    // Aucune donnée sensible (codes ISO, devises, fuseaux, confidenceLevel).
    Route::middleware(['throttle:public-registry'])->get('/supported-countries', [SupportedCountryController::class, 'index']);

    // RESTO-805 (#6226) — boutique publique RestaurantManager (jeton signé par
    // tenant, sans auth utilisateur) — throttling renforcé `shop-public` +
    // hook anti-bot CAPTCHA configurable (pattern TRAVEL-1001/#6114).
    Route::middleware(['throttle:shop-public', 'restaurant.public.shop'])->group(function (): void {
        Route::get('/public/restaurant/shop/menu', [RestaurantPublicShopController::class, 'menu']);
        Route::post('/public/restaurant/shop/orders', [RestaurantPublicShopController::class, 'storeOrder']);
        Route::get('/public/restaurant/shop/orders/{reference}', [RestaurantPublicShopController::class, 'track']);
        Route::post('/public/restaurant/shop/orders/{reference}/pay', [RestaurantPublicShopController::class, 'initiatePayment']);
    });

    // RESTO-807 (#6228) — kiosque libre-service (même jeton boutique, web).
    Route::middleware(['throttle:shop-public', 'restaurant.public.shop'])->group(function (): void {
        Route::get('/public/restaurant/kiosk/menu', [RestaurantKioskController::class, 'menu']);
        Route::post('/public/restaurant/kiosk/orders', [RestaurantKioskController::class, 'storeOrder']);
        Route::get('/public/restaurant/kiosk/orders/{reference}', [RestaurantKioskController::class, 'track']);
    });

    Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan'])->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::patch('/auth/profile', [AuthController::class, 'updateProfile']);
        Route::patch('/auth/language', [AuthController::class, 'updateLanguage']);
        Route::post('/auth/change-password', [AuthController::class, 'changePassword']);
        // #5436 : gestion 2FA du compte connecté.
        Route::get('/auth/2fa/status', [TwoFactorAuthController::class, 'status']);
        Route::post('/auth/2fa/enroll', [TwoFactorAuthController::class, 'enroll']);
        Route::post('/auth/2fa/confirm', [TwoFactorAuthController::class, 'confirm']);
        Route::post('/auth/2fa/disable', [TwoFactorAuthController::class, 'disable']);
        Route::post('/auth/2fa/recovery-codes', [TwoFactorAuthController::class, 'recoveryCodes']);
        Route::post('/auth/refresh-token', [AuthController::class, 'refreshToken']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/client-events', [ClientEventController::class, 'store'])->middleware('throttle:client-analytics');
        Route::get('/notification-preferences', [NotificationPreferenceController::class, 'show']);
        Route::patch('/notification-preferences', [NotificationPreferenceController::class, 'update']);
        Route::get('/communication/analytics', CommunicationAnalyticsController::class)->middleware('throttle:platform-sensitive');
        Route::get('/launch-readiness', LaunchReadinessController::class);
        Route::get('/auth/biometric-enrollment', [BiometricEnrollmentController::class, 'myStatus']);
        Route::post('/auth/biometric-enrollment', [BiometricEnrollmentController::class, 'store']);
        Route::middleware(['throttle:privacy-sensitive'])->group(function (): void {
            Route::get('/privacy/export', [PrivacyController::class, 'export']);
            Route::post('/privacy/deletion-request', [PrivacyController::class, 'storeDeletionRequest']);
            Route::patch('/privacy/biometric-consent', [PrivacyController::class, 'updateBiometricConsent']);
        });

        // Feature Registry API - Mobile synchronization
        Route::prefix('features')->group(function (): void {
            Route::get('/manifest', [FeatureManifestController::class, 'index']);
            Route::get('/compatible/{version}', [FeatureManifestController::class, 'compatible']);
            Route::get('/{key}', [FeatureManifestController::class, 'show']);

            // Admin only endpoints
            Route::middleware(['admin'])->group(function (): void {
                Route::get('/admin/statistics', [FeatureManifestController::class, 'statistics']);
                Route::post('/admin/synchronize', [FeatureManifestController::class, 'synchronize']);
            });
        });

        // Company requests for ordinary users
        Route::get('/company-requests', [CompanyRequestController::class, 'index']);
        Route::post('/company-requests', [CompanyRequestController::class, 'store']);
        Route::get('/company/branding', [CompanyBrandingController::class, 'show']);
        Route::patch('/company/branding', [CompanyBrandingController::class, 'update']);
        // Issue #5613 — Coordonnées bancaires SEPA (IBAN/BIC entreprise).
        Route::get('/company/banking', [CompanyBankingController::class, 'show']);
        Route::patch('/company/banking', [CompanyBankingController::class, 'update']);

        // #7322 — le client (responsable du tenant) active lui-même un module
        // HORIZONTAL de son entreprise : `metadata.modules` + flag plateforme
        // miroir. Allowlist fail-closed (Company::HORIZONTAL_TOOLS) et RBAC
        // principal/rh appliqués dans le contrôleur ; les verticales restent
        // hors périmètre (seeders/dépendances de pack, admin plateforme).
        Route::post('/company/modules/{module}/activate', [CompanyModuleController::class, 'activate'])
            ->where('module', '[a-z_]{1,40}');

        // PA2-COMM-012 — Pilot client support center. #7761 : la gestion des
        // tickets devient DÉLÉGABLE comme n'importe quel module (grant
        // `support`) : accès = tout employé du tenant porteur du grant OU tout
        // manager (comportement historique préservé pour les managers, le
        // principal ayant implicitement tout) ; un employé non-manager sans
        // grant est désormais refusé (fail-closed, MODULE_ACCESS_REQUIRED).
        Route::middleware('api.module.grant:support')->group(function (): void {
            Route::get('/support-tickets', [SupportTicketController::class, 'index']);
            Route::post('/support-tickets', [SupportTicketController::class, 'store']);
            Route::get('/support-tickets/{supportTicket}', [SupportTicketController::class, 'show'])->whereNumber('supportTicket');
            Route::post('/support-tickets/{supportTicket}/reply', [SupportTicketController::class, 'reply'])->whereNumber('supportTicket');
            // #4933 : clôture par l'auteur ou un manager du tenant.
            Route::post('/support-tickets/{supportTicket}/close', [SupportTicketController::class, 'close'])->whereNumber('supportTicket');
        });

        // DEPRECATED (#4929) : endpoint de « go-live readiness » calculé (8
        // étapes auto-détectées) — distinct de la checklist pilotée par la
        // table onboarding_steps. Le contrat canonique consommé par web et
        // mobile est GET /onboarding-setup/checklist + PATCH …/{stepKey}/
        // complete|skip. Cet endpoint est conservé pour les clients existants.
        Route::get('/onboarding/checklist', OnboardingChecklistController::class);

        // #7604 (tranche du critère 2 de #7490) — écran de bienvenue de
        // première connexion : l'acquittement est persisté côté SERVEUR
        // (`public.companies.metadata.welcome_seen_at`), jamais en
        // `localStorage` — l'écran ne se réaffiche donc pas sur un autre
        // appareil. RBAC responsable (principal/rh) appliqué dans le
        // contrôleur ; la lecture de l'état se fait par `/auth/me`
        // (`company.metadata`), il n'y a pas de route de lecture à ajouter.
        Route::post('/onboarding/welcome-ack', WelcomeScreenController::class);

        // #7493 — entretien de préparation conversationnel (première
        // connexion, après l'écran de bienvenue #7490) : une question à la
        // fois, zappable, reprenable. Brouillon SERVEUR
        // (`public.companies.metadata.setup_interview`, exposé par `/auth/me`) ;
        // la clôture active les modules selon les réponses (allowlist
        // fail-closed, `SolutionActivator` idempotent). RBAC principal/rh
        // appliqué dans le contrôleur.
        Route::get('/setup-interview', [SetupInterviewController::class, 'show']);
        Route::patch('/setup-interview/answers', [SetupInterviewController::class, 'saveAnswers']);
        Route::post('/setup-interview/complete', [SetupInterviewController::class, 'complete']);
        Route::post('/setup-interview/dismiss', [SetupInterviewController::class, 'dismiss']);

        // #7865 — jeu de données de démonstration à la demande du client :
        // une entrée par verticale ACTIVE du tenant (kits enregistrés par les
        // modules dans `DemoDataRegistry`, allowlist fail-closed). L'import
        // rejoue les seeders idempotents RESTO-107/TRAVEL-107 et persiste
        // l'état dans `public.companies.metadata.demo_data` (exposé par
        // `/auth/me`) ; `dismiss` = « non merci », jamais bloquant. RBAC
        // principal/rh appliqué dans le contrôleur ; l'import (coûteux) est
        // throttlé par un seau dédié.
        Route::get('/demo-data', [DemoDataController::class, 'index']);
        Route::post('/demo-data/{code}/import', [DemoDataController::class, 'import'])
            ->where('code', '[a-z_]{1,40}')
            ->middleware('throttle:demo-data-import');
        Route::post('/demo-data/{code}/dismiss', [DemoDataController::class, 'dismiss'])
            ->where('code', '[a-z_]{1,40}');
    });

    // APV L.08 — Modules Leopardo, chaque module a son propre route group.
    // RH est le module de base : toujours charge. Les autres modules Phase 2
    // (finance, cameras, muhasebe, leo_ai) seront inclus ici derriere un gate
    // companies.features lors de leur implementation.
    require __DIR__.'/modules/rh.php';
    require __DIR__.'/modules/hr_extended.php';
    require __DIR__.'/modules/payroll_engine.php';
    require __DIR__.'/modules/cameras.php';
    require __DIR__.'/modules/cabinet.php';
    require __DIR__.'/modules/accounting.php';
    require __DIR__.'/modules/user.php';
    require __DIR__.'/modules/tracking.php';
    require __DIR__.'/modules/dashboard.php';
    require __DIR__.'/modules/planning.php';
    require __DIR__.'/modules/crm.php';

    require __DIR__.'/modules/billing.php';
    require __DIR__.'/modules/sso.php';
    require __DIR__.'/modules/integrations.php';
    require __DIR__.'/modules/growth.php';

    // Phase 2 — New DDD modules
    require __DIR__.'/modules/absence.php';
    require __DIR__.'/modules/expense.php';
    require __DIR__.'/modules/marketing.php';
    require __DIR__.'/modules/restaurantmanager.php';

    require __DIR__.'/modules/travelagency.php';
    require __DIR__.'/modules/showcase.php';
    require __DIR__.'/modules/fuel_station.php';
    require __DIR__.'/modules/edu_manager.php';

    // PHARMA-001 (#7798) — verticale PharmaManager (officines de pharmacie) :
    // routes tenant-scoped derrière le feature flag `pharmacy` (fail-closed).
    require __DIR__.'/modules/pharmacy.php';
    require __DIR__.'/modules/catalog.php';

    // BC-17 RETAIL #7672 — module vendeur générique (produits & catégories)
    require __DIR__.'/modules/retail.php';
    // BC-29 COMMUNICATION — boîte mail connectée + IA, squelette R0 (#7685)
    require __DIR__.'/modules/communication.php';

    // C-PUBLIC #6882 — catalogue public (routes isolées, sans auth)
    require __DIR__.'/modules/catalog_public.php';
    // BC-17 #7807/#7808 — marketplace publique Leopardo Marché (routes isolées, sans auth)
    require __DIR__.'/modules/market_public.php';
    require __DIR__.'/modules/solutions.php';

    // Multi-App dedicated route modules
    require __DIR__.'/modules/hr_app.php';

    // BC-26 DELIVERY — module de livraison générique (DELIVERY-101/#6282)
    require __DIR__.'/modules/delivery.php';

    // IA Module — fichier requis DANS le groupe v1 (prefix /api/v1) :
    // chemins réels /api/v1/ai/* (drift doc #4936)
    require __DIR__.'/ai.php';

    // Platform (super-admin, hors module)
    Route::middleware(['auth:super_admin_api', 'throttle:platform-sensitive'])->prefix('platform')->group(function (): void {
        Route::get('/auth/me', [PlatformAuthController::class, 'me']);
        Route::patch('/auth/profile', [PlatformAuthController::class, 'updateProfile']);
        Route::post('/auth/change-password', [PlatformAuthController::class, 'changePassword']);
        Route::post('/auth/logout', [PlatformAuthController::class, 'logout']);

        // 2FA Super-Admin
        Route::post('/auth/2fa/setup', [PlatformAuthController::class, 'setup2fa']);
        Route::post('/auth/2fa/enable', [PlatformAuthController::class, 'enable2fa']);
        Route::post('/auth/2fa/disable', [PlatformAuthController::class, 'disable2fa']);
        // #7430 (BC-21 BILLING) — les offres sont PARAMÉTRABLES : le seeder
        // n'est plus le seul chemin. Création, édition (prix, limite
        // d'employés, matrice de features), duplication, archivage, et
        // suppression REFUSÉE (409) dès qu'une offre est utilisée — elle
        // s'archive. Chaque écriture est auditée (AuditLog, société nulle).
        Route::post('/plans', [PlatformPlanAdminController::class, 'store']);
        Route::patch('/plans/{plan}', [PlatformPlanAdminController::class, 'update'])->whereNumber('plan');
        Route::post('/plans/{plan}/duplicate', [PlatformPlanAdminController::class, 'duplicate'])->whereNumber('plan');
        Route::post('/plans/{plan}/archive', [PlatformPlanAdminController::class, 'archive'])->whereNumber('plan');
        Route::delete('/plans/{plan}', [PlatformPlanAdminController::class, 'destroy'])->whereNumber('plan');
        Route::get('/plans', PlatformPlanController::class)->middleware('platform.permission:plans.view');
        Route::get('/country-defaults', PlatformCountryDefaultsController::class);
        Route::get('/companies', [PlatformCompanyController::class, 'index'])->middleware('platform.permission:companies.view');
        Route::post('/companies', [PlatformCompanyController::class, 'store'])->middleware('platform.permission:companies.provision');
        Route::get('/companies/health', [PlatformCompanyHealthController::class, 'index'])->middleware('platform.permission:companies.view');
        Route::get('/companies/{company}/health', PlatformCompanyHealthController::class)->middleware('platform.permission:companies.view');
        // #7475 — suppression sûre d'un tenant : parcours en deux temps
        // (désactivation d'abord), inventaire chiffré, confirmation par
        // ressaisie du nom exact, journalisation dans
        // `public.tenant_deletion_audits` (qui survit à la purge).
        Route::get('/companies/{company}/deletion-inventory', [PlatformCompanyDeletionController::class, 'inventory']);
        Route::get('/companies/{company}/deletion-audits', [PlatformCompanyDeletionController::class, 'history']);
        // #7576 — la piste d'audit survit à la purge : lecture plateforme, non
        // scopée à une entreprise vivante (sinon la preuve est inexploitable).
        Route::get('/tenant-deletion-audits', [PlatformCompanyDeletionController::class, 'auditTrail'])->middleware('platform.permission:companies.view');
        Route::delete('/companies/{company}', [PlatformCompanyDeletionController::class, 'destroy']);
        // MULTI-PAYS (#1952) : réparation/choix du pays d'un tenant legacy
        // (refusé si données de paie — invariant 9). #7680 : les doublons SANS
        // platform.permission masquaient ces versions protégées (Laravel matche
        // la première route enregistrée) — supprimés, granularité rétablie.
        Route::patch('/companies/{company}/country', [PlatformCompanyController::class, 'updateCountry'])->middleware('platform.permission:companies.manage');
        Route::get('/companies/{company}/subscription', [PlatformCompanySubscriptionController::class, 'show'])->middleware('platform.permission:billing.view');
        Route::patch('/companies/{company}/subscription', [PlatformCompanySubscriptionController::class, 'update'])->middleware('platform.permission:billing.manage');

        // #7726 (BC-21 BILLING) — configuration des passerelles de paiement
        // (Stripe/Chargily) depuis l'admin : stockage chiffré en BDD,
        // précédence BDD → fallback env, secrets write-only jamais renvoyés
        // en clair (masque), test de connexion. Permission `billing.manage`.
        Route::get('/billing/gateways', [PlatformPaymentGatewayController::class, 'index'])->middleware('platform.permission:billing.manage');
        Route::put('/billing/gateways', [PlatformPaymentGatewayController::class, 'update'])->middleware('platform.permission:billing.manage');
        Route::post('/billing/gateways/{gateway}/test', [PlatformPaymentGatewayController::class, 'test'])
            ->where('gateway', '[a-z]+')->middleware('platform.permission:billing.manage');
        Route::get('/companies/{company}/features', [PlatformCompanyFeatureController::class, 'show'])->middleware('platform.permission:companies.view');
        Route::patch('/companies/{company}/features', [PlatformCompanyFeatureController::class, 'update'])->middleware('platform.permission:companies.manage');

        // MAT-010 (#5868) — Feature kill switches : stopper un module pour
        // toute la plateforme (fail-closed, sans suppression de données).
        // Bascules idempotentes + audit (feature_kill_switches + canal audit).
        Route::get('/feature-kill-switches', [PlatformFeatureKillSwitchController::class, 'index'])->middleware('platform.permission:killswitch.manage');
        Route::post('/feature-kill-switches', [PlatformFeatureKillSwitchController::class, 'activate'])->middleware('platform.permission:killswitch.manage');
        Route::delete('/feature-kill-switches/{key}', [PlatformFeatureKillSwitchController::class, 'deactivate'])
            ->where('key', '[A-Za-z0-9_.-]+')->middleware('platform.permission:killswitch.manage');
        Route::get('/metrics/overview', PlatformMetricsOverviewController::class)->middleware('platform.permission:metrics.view');

        // PA2-QA-006 — Redis/jobs observability (queue depth, failed jobs,
        // scheduled task last-run) for the super-admin "System" screen.
        Route::get('/observability/queues', QueueObservabilityController::class)->middleware('platform.permission:observability.view');

        // PA2-ADM-005 — Cross-tenant notification failure rate (24h) +
        // curated runbook links for the super-admin "System" screen.
        Route::get('/observability/notifications', PlatformNotificationObservabilityController::class)->middleware('platform.permission:observability.view');

        Route::get('/company-requests', [PlatformCompanyRequestController::class, 'index'])->middleware('platform.permission:companies.view');
        Route::get('/company-requests/{id}', [PlatformCompanyRequestController::class, 'show'])->whereNumber('id')->middleware('platform.permission:companies.view');
        Route::patch('/company-requests/{id}', [PlatformCompanyRequestController::class, 'updateStatus'])->whereNumber('id')->middleware('platform.permission:companies.provision');

        Route::get('/crm/pipeline', PlatformCrmPipelineController::class)->middleware('platform.permission:crm.view');

        // Tranche #7595 — les leads d'acquisition de la vitrine etaient ecrits
        // (POST /marketing/leads) et JAMAIS relus : aucune route GET n'existait.
        // Lecture seule, meme garde que le pipeline CRM.
        Route::get('/marketing/leads', [PlatformMarketingLeadController::class, 'index'])->middleware('platform.permission:crm.view');

        // PA2-COMM-012 — Pilot client support center: super-admin triage of
        // tenant-opened support tickets (status, priority, assignment, reply).
        Route::get('/support-tickets', [PlatformSupportTicketController::class, 'index'])->middleware('platform.permission:support.manage');
        Route::get('/support-tickets/{supportTicket}', [PlatformSupportTicketController::class, 'show'])->whereNumber('supportTicket')->middleware('platform.permission:support.manage');
        Route::post('/support-tickets/{supportTicket}/reply', [PlatformSupportTicketController::class, 'reply'])->whereNumber('supportTicket')->middleware('platform.permission:support.manage');
        Route::patch('/support-tickets/{supportTicket}/triage', [PlatformSupportTicketController::class, 'triage'])->whereNumber('supportTicket')->middleware('platform.permission:support.manage');

        // PA2-COMM-005 — Platform-wide announcements (maintenance, feature,
        // incident, action required) broadcast by super-admin to all or a
        // selected subset of companies.
        Route::get('/announcements', [PlatformAnnouncementController::class, 'index'])->middleware('platform.permission:announcements.manage');
        Route::post('/announcements', [PlatformAnnouncementController::class, 'store'])->middleware('platform.permission:announcements.manage');
        Route::get('/announcements/{announcement}', [PlatformAnnouncementController::class, 'show'])->middleware('platform.permission:announcements.manage');
        Route::delete('/announcements/{announcement}', [PlatformAnnouncementController::class, 'destroy'])->middleware('platform.permission:announcements.manage');

        // QA wave 2026-08-14 — T004 (#2229) : CRUD utilisateurs plateforme.
        Route::get('/users', [PlatformUserController::class, 'index'])->middleware('platform.permission:users.view');
        Route::post('/users', [PlatformUserController::class, 'store'])->middleware('platform.permission:users.manage');
        Route::get('/users/{user}', [PlatformUserController::class, 'show'])->whereNumber('user')->middleware('platform.permission:users.view');
        Route::patch('/users/{user}', [PlatformUserController::class, 'update'])->whereNumber('user')->middleware('platform.permission:users.manage');
        Route::delete('/users/{user}', [PlatformUserController::class, 'destroy'])->whereNumber('user')->middleware('platform.permission:users.manage');
        Route::post('/users/{user}/activate', [PlatformUserController::class, 'activate'])->whereNumber('user')->middleware('platform.permission:users.manage');
        Route::post('/users/{user}/deactivate', [PlatformUserController::class, 'deactivate'])->whereNumber('user')->middleware('platform.permission:users.manage');
        Route::post('/users/{user}/suspend', [PlatformUserController::class, 'suspend'])->whereNumber('user')->middleware('platform.permission:users.manage');

        // #7553 — Équipe interne de la plateforme : le super admin délègue
        // (support, finance, ops, marketing) au lieu de partager le compte
        // omniscient. `team.manage` n'est porté que par le rôle `super_admin`.
        Route::prefix('team')->middleware('platform.permission:team.manage')->group(function (): void {
            Route::get('/', [PlatformTeamController::class, 'index']);
            Route::post('/', [PlatformTeamController::class, 'store']);
            Route::patch('/{superAdmin}/role', [PlatformTeamController::class, 'updateRole'])->whereNumber('superAdmin');
            Route::post('/{superAdmin}/activate', [PlatformTeamController::class, 'activate'])->whereNumber('superAdmin');
            Route::post('/{superAdmin}/deactivate', [PlatformTeamController::class, 'deactivate'])->whereNumber('superAdmin');
        });

        // PA2-ADM-006 — Secure super-admin impersonation ("log in as this
        // employee"): mandatory reason, hard time limit, fully audited.
        Route::get('/impersonations', [PlatformImpersonationController::class, 'index'])->middleware('platform.permission:impersonate');
        Route::post('/impersonations', [PlatformImpersonationController::class, 'store'])->middleware('platform.permission:impersonate');
        Route::delete('/impersonations/{session}', [PlatformImpersonationController::class, 'destroy'])->whereNumber('session')->middleware('platform.permission:impersonate');

        // Edge node management (super-admin).
        // Uses EdgeNodeController against the canonical UUID edge_nodes
        // schema (see issue #1291) — the legacy bigint-schema EdgeController
        // equivalents were removed because that schema is never created.
        Route::prefix('edge/nodes')->group(function (): void {
            Route::get('/', [EdgeNodeController::class, 'listAllNodes'])->middleware('platform.permission:edge.manage');
            Route::post('/{nodeId}/sync', [EdgeNodeController::class, 'forceSync'])->middleware('platform.permission:edge.manage');
            Route::delete('/{nodeId}', [EdgeNodeController::class, 'revokeNode'])->middleware('platform.permission:edge.manage');
        });
    });
    // Admin cockpit (super-admin) — contrat SPA front/admin-dashboard.
    // Endpoints appelés par le SPA sans exister côté API (issue #1764) :
    // création côté API des routes manquantes (décision produit).
    Route::middleware(['auth:super_admin_api', 'throttle:platform-sensitive'])->prefix('admin')->group(function (): void {
        // Issue #2624 : impersonation super-admin aussi sous /admin (le SPA
        // admin-dashboard consomme /admin/*) — réutilise le contrôleur
        // platform existant (PA2-ADM-006).
        Route::get('/impersonations', [PlatformImpersonationController::class, 'index'])->middleware('platform.permission:impersonate');
        Route::post('/impersonations', [PlatformImpersonationController::class, 'store'])->middleware('platform.permission:impersonate');
        Route::delete('/impersonations/{session}', [PlatformImpersonationController::class, 'destroy'])->whereNumber('session')->middleware('platform.permission:impersonate');

        Route::get('/dashboard/stats', [PlatformAdminDashboardController::class, 'stats'])->middleware('platform.permission:metrics.view');
        Route::get('/dashboard/activities', [PlatformAdminDashboardController::class, 'activities'])->middleware('platform.permission:metrics.view');
        Route::get('/dashboard/alerts', [PlatformAdminDashboardController::class, 'alerts'])->middleware('platform.permission:metrics.view');
        Route::post('/dashboard/alerts/{alertKey}/dismiss', [PlatformAdminDashboardController::class, 'dismissAlert'])
            ->where('alertKey', '[A-Za-z0-9\-_]+')->middleware('platform.permission:metrics.view');

        // Edge nodes : réutilisation du contrôleur EdgeSync existant
        // (listAllNodes / forceSync / revokeNode) — alias des routes
        // /platform/edge/nodes pour le contrat SPA /admin/edge-nodes.
        Route::get('/edge-nodes', [EdgeNodeController::class, 'listAllNodes']);
        Route::post('/edge-nodes/{nodeId}/sync', [EdgeNodeController::class, 'forceSync']);
        Route::post('/edge-nodes/{nodeId}/revoke', [EdgeNodeController::class, 'revokeNode']);

        Route::get('/ai/conversations', [PlatformAdminAiConversationController::class, 'index']);
        Route::get('/ai/conversations/{conversation}/messages', [PlatformAdminAiConversationController::class, 'messages'])
            ->whereNumber('conversation');
        Route::post('/ai/chat', [PlatformAdminAiConversationController::class, 'chat']);

        // BC-25 #6694 — pilotage des surveys de solutions (stats de conversion
        // du wizard vitrine, agrégées depuis marketing_leads type solution_survey).
        Route::get('/solutions/survey-stats', [PlatformSolutionSurveyStatsController::class, 'index']);
        Route::get('/marketing/leads', [PlatformMarketingLeadController::class, 'index'])->middleware('platform.permission:crm.view');

        // #7496 — conversions du funnel d'acquisition par étape/jour/source
        // (dashboard admin CRM commercial, données acquisition_funnel_events).
        Route::get('/funnel/stats', [PlatformAcquisitionFunnelController::class, 'index'])->middleware('platform.permission:metrics.view');

        Route::get('/fleet/alerts', [PlatformAdminFleetAlertController::class, 'index']);

        Route::get('/hr-reports', [PlatformHrReportController::class, 'generate']);

        // Issue #2634 : équivalents /admin des vues Training et Webhooks
        // (les routes tenant /training/* et /webhooks* sont api.manager → 401 super-admin).
        Route::get('/training/courses', [PlatformAdminTrainingController::class, 'indexCourses']);
        Route::get('/training/sessions', [PlatformAdminTrainingController::class, 'indexSessions']);
        Route::get('/training/enrollments', [PlatformAdminTrainingController::class, 'indexEnrollments']);
        Route::get('/webhooks', [PlatformAdminWebhookController::class, 'index']);
        Route::get('/webhooks/events', [PlatformAdminWebhookController::class, 'events']);
        Route::post('/webhooks', [PlatformAdminWebhookController::class, 'store']);
        Route::get('/webhooks/{webhookEndpoint}', [PlatformAdminWebhookController::class, 'show'])->whereNumber('webhookEndpoint');
        Route::put('/webhooks/{webhookEndpoint}', [PlatformAdminWebhookController::class, 'update'])->whereNumber('webhookEndpoint');
        Route::patch('/webhooks/{webhookEndpoint}', [PlatformAdminWebhookController::class, 'update'])->whereNumber('webhookEndpoint');
        Route::delete('/webhooks/{webhookEndpoint}', [PlatformAdminWebhookController::class, 'destroy'])->whereNumber('webhookEndpoint');
        Route::post('/webhooks/{webhookEndpoint}/test', [PlatformAdminWebhookController::class, 'test'])->whereNumber('webhookEndpoint');
        Route::get('/webhooks/{webhookEndpoint}/dead-letters', [PlatformAdminWebhookController::class, 'deadLetters'])->whereNumber('webhookEndpoint');
        Route::post('/webhooks/{webhookEndpoint}/dead-letters/{delivery}/replay', [PlatformAdminWebhookController::class, 'replayDeadLetter'])->whereNumber('webhookEndpoint')->whereNumber('delivery');

        // #7347 — édition des contenus d'e-mails depuis la plateforme admin
        // (Paramètres › E-mails). Surcharge par (template, locale) ; sans
        // surcharge, l'e-mail garde sa valeur par défaut du catalogue.
        Route::get('/email-templates', [PlatformEmailTemplateController::class, 'index']);
        Route::put('/email-templates', [PlatformEmailTemplateController::class, 'update']);
        Route::delete('/email-templates', [PlatformEmailTemplateController::class, 'reset']);
        Route::post('/email-templates/preview', [PlatformEmailTemplateController::class, 'preview']);

        Route::get('/platform/marketing/oauth-config', [PlatformMarketingOAuthConfigController::class, 'index']);
        Route::put('/platform/marketing/oauth-config', [PlatformMarketingOAuthConfigController::class, 'update']);

        // #7384 — assistant IA : réglages éditables depuis le cockpit. Les
        // valeurs sensibles sont chiffrées au repos et ne sont JAMAIS renvoyées.
        Route::get('/platform/ai/settings', [PlatformAiSettingsController::class, 'index']);
        Route::put('/platform/ai/settings', [PlatformAiSettingsController::class, 'update']);
        Route::post('/platform/ai/settings/reset', [PlatformAiSettingsController::class, 'reset']);
        // Test à blanc du fournisseur : valider une clé AVANT d'activer l'IA.
        Route::post('/platform/ai/test-connection', [PlatformAiSettingsController::class, 'testConnection']);

        // #7385 — suivi de l'assistant, tous tenants (usage, coûts, erreurs).
        Route::get('/platform/ai/monitoring', [PlatformAiMonitoringController::class, 'index']);
        Route::get('/platform/ai/health', [PlatformAiMonitoringController::class, 'health']);

        // Public holidays (issue #1811) — super-admin : CRUD fériés nationaux.
        Route::get('/public-holidays', [PublicHolidayController::class, 'index']);
        Route::post('/public-holidays', [PublicHolidayController::class, 'store']);
        Route::put('/public-holidays/{publicHoliday}', [PublicHolidayController::class, 'update'])->whereNumber('publicHoliday');
        Route::delete('/public-holidays/{publicHoliday}', [PublicHolidayController::class, 'destroy'])->whereNumber('publicHoliday');

        // Islamic calendar (issue #1812) — super-admin : dates mobiles des
        // fêtes islamiques (Aïd, Maouloud, Tamkharit…) par année.
        Route::get('/islamic-calendar', [IslamicCalendarController::class, 'index']);
        Route::post('/islamic-calendar/confirm-year/{year}', [IslamicCalendarController::class, 'confirmYear'])->whereNumber('year');
        Route::put('/islamic-calendar/{holidayKey}/{year}', [IslamicCalendarController::class, 'update'])
            ->whereIn('holidayKey', ['eid_al_fitr', 'eid_al_adha', 'mawlid', 'tahmarit', 'muharram'])
            ->whereNumber('year');

        // Issue #1814 — barèmes fiscaux nationaux (CRUD admin) + simulation.
        Route::get('/tax-slabs', [TaxSlabAdminController::class, 'index']);
        Route::post('/tax-slabs', [TaxSlabAdminController::class, 'store']);
        Route::put('/tax-slabs/{taxSlab}', [TaxSlabAdminController::class, 'update'])->whereNumber('taxSlab');
        Route::delete('/tax-slabs/{taxSlab}', [TaxSlabAdminController::class, 'destroy'])->whereNumber('taxSlab');
        Route::post('/tax-slabs/reset-defaults', [TaxSlabAdminController::class, 'resetDefaults']);
        Route::post('/payroll/simulate', [PayrollSimulationController::class, 'simulate']);

        // Issue #1874 — audit des calculs de paie (vue plateforme, cross-tenant :
        // filtre company_id optionnel ; le platform_admin est autorisé par
        // PayrollAuditPolicy — pattern #1917).
        Route::get('/payroll/audit', [PayrollAuditController::class, 'index']);
        Route::get('/payroll/audit/{correlationId}', [PayrollAuditController::class, 'show'])->whereUuid('correlationId');

        // Issue #1815 — cotisations sociales nationales (CRUD admin).
        Route::get('/social-contributions', [SocialContributionAdminController::class, 'index']);
        Route::post('/social-contributions', [SocialContributionAdminController::class, 'store']);
        Route::put('/social-contributions/{socialContribution}', [SocialContributionAdminController::class, 'update'])->whereNumber('socialContribution');
        Route::delete('/social-contributions/{socialContribution}', [SocialContributionAdminController::class, 'destroy'])->whereNumber('socialContribution');

        // Issue #1813 — validation des modifications de taux légaux
        // (approbation/rejet réservés au platform_admin).
        Route::get('/rate-validation/pending', [RateValidationAdminController::class, 'pending']);
        Route::put('/rate-validation/{table}/{id}/approve', [RateValidationAdminController::class, 'approve'])
            ->whereIn('table', ['tax_slabs', 'social_contributions'])->whereNumber('id');
        Route::put('/rate-validation/{table}/{id}/reject', [RateValidationAdminController::class, 'reject'])
            ->whereIn('table', ['tax_slabs', 'social_contributions'])->whereNumber('id');

        // Issue #2269 — gestion des utilisateurs plateforme (contrat SPA
        // UsersView/UserDetailView réels, plus de mocks).
        Route::get('/users', [PlatformUsersController::class, 'index']);
        Route::get('/users/{user}', [PlatformUsersController::class, 'show'])->whereNumber('user');
        Route::patch('/users/{user}', [PlatformUsersController::class, 'update'])->whereNumber('user');
    });
});
