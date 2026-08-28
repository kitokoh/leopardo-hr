<?php

use App\Core\Auth\Interfaces\Api\V1\AuthController;
use App\Core\Auth\Interfaces\Api\V1\TwoFactorAuthController;
use App\Core\Auth\Interfaces\Api\V1\PasswordResetController;
use App\Core\Auth\Interfaces\Api\V1\PlatformAuthController;
use App\Core\Feature\Interfaces\Api\V1\FeatureManifestController;
use App\Http\Controllers\AI\VoiceController;
use App\Http\Controllers\Web\PlatformCompanyController;
use App\Modules\Attendance\Interfaces\Api\V1\BiometricEnrollmentController;
use App\Modules\Billing\Interfaces\Api\V1\CompanyRequestController;
use App\Modules\Billing\Interfaces\Api\V1\PaymentWebhookController;
use App\Modules\Billing\Interfaces\Api\V1\PlatformCompanySubscriptionController;
use App\Modules\Billing\Interfaces\Api\V1\PlatformPlanController;
use App\Modules\Billing\Interfaces\Api\V1\SelfServiceTrialController;
use App\Modules\Billing\Interfaces\Api\V1\StripeWebhookController;
use App\Modules\EdgeSync\Interfaces\Api\V1\EdgeNodeController;
use App\Modules\HR\Interfaces\Api\V1\Controllers\CompanyBankingController;
use App\Modules\HR\Interfaces\Api\V1\Controllers\CompanyBrandingController;
use App\Modules\HR\Interfaces\Api\V1\Controllers\PrivacyController;
use App\Modules\Marketing\Interfaces\Api\V1\Controllers\MarketingLeadController;
use App\Modules\Notification\Interfaces\Api\V1\Controllers\EmailBounceWebhookController;
use App\Modules\Notification\Interfaces\Api\V1\Controllers\NotificationPreferenceController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingPaymentWebhookController;
use App\Modules\Onboarding\Interfaces\Api\V1\Controllers\OnboardingChecklistController;
use App\Modules\Onboarding\Interfaces\Api\V1\Controllers\OnboardingController;
use App\Modules\Payroll\Interfaces\Api\V1\IslamicCalendarController;
use App\Modules\Payroll\Interfaces\Api\V1\PayrollAuditController;
use App\Modules\Payroll\Interfaces\Api\V1\PayrollSimulationController;
use App\Modules\Payroll\Interfaces\Api\V1\PublicHolidayController;
use App\Modules\Payroll\Interfaces\Api\V1\RateValidationAdminController;
use App\Modules\Payroll\Interfaces\Api\V1\SocialContributionAdminController;
use App\Modules\Payroll\Interfaces\Api\V1\TaxSlabAdminController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\ClientEventController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\CommunicationAnalyticsController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\DemoUserController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\HealthController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\LaunchReadinessController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\MetricsController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformAdminAiConversationController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformAdminDashboardController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformAdminFleetAlertController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformAdminTrainingController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformAdminWebhookController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformAnnouncementController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformCompanyFeatureController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformCompanyHealthController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformCompanyRequestController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformCountryDefaultsController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformCrmPipelineController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformHrReportController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformImpersonationController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformMarketingOAuthConfigController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformMetricsOverviewController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformNotificationObservabilityController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformSupportTicketController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformUserController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformUsersController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\QueueObservabilityController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\SupportedCountryController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\SupportTicketController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\TranslationCatalogController;
use App\Modules\Recruitment\Interfaces\Api\V1\CandidateApplicationController;
use App\Modules\Recruitment\Interfaces\Api\V1\PublicCareerController;
use Illuminate\Support\Facades\Route;

// Edge routes are now registered by EdgeSyncServiceProvider

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
        Route::get('/auth/google', [AuthController::class, 'redirectToGoogle']);
        Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);
        Route::post('/auth/google/token', [AuthController::class, 'handleGoogleToken']);
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
    Route::middleware(['throttle:5,15'])->group(function (): void {
        Route::post('/trial/signup', [SelfServiceTrialController::class, 'signup']);
        Route::post('/trial/verify', [SelfServiceTrialController::class, 'verify']);
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

    // Stripe/Chargily webhooks (public, verified by provider signature inside
    // the controller). PA2-API-005: dedicated 'webhooks-inbound' throttle since
    // these routes sit outside the authenticated 'api' middleware group below.
    Route::middleware(['throttle:webhooks-inbound'])->group(function (): void {
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

        // PA2-COMM-012 — Pilot client support center: a manager/employee can
        // open a support ticket and reply on their own company's tickets.
        Route::get('/support-tickets', [SupportTicketController::class, 'index']);
        Route::post('/support-tickets', [SupportTicketController::class, 'store']);
        Route::get('/support-tickets/{supportTicket}', [SupportTicketController::class, 'show'])->whereNumber('supportTicket');
        Route::post('/support-tickets/{supportTicket}/reply', [SupportTicketController::class, 'reply'])->whereNumber('supportTicket');
        // #4933 : clôture par l'auteur ou un manager du tenant.
        Route::post('/support-tickets/{supportTicket}/close', [SupportTicketController::class, 'close'])->whereNumber('supportTicket');

        // DEPRECATED (#4929) : endpoint de « go-live readiness » calculé (8
        // étapes auto-détectées) — distinct de la checklist pilotée par la
        // table onboarding_steps. Le contrat canonique consommé par web et
        // mobile est GET /onboarding-setup/checklist + PATCH …/{stepKey}/
        // complete|skip. Cet endpoint est conservé pour les clients existants.
        Route::get('/onboarding/checklist', OnboardingChecklistController::class);
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

    // CRM client tenant — canaux/automatisations/exports (issues #5725, #5727,
    // #5728, #5729). Webhooks publics signature HMAC + routes api.manager.
    require __DIR__.'/modules/crm.php';

    // Multi-App dedicated route modules
    require __DIR__.'/modules/hr_app.php';

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
        Route::get('/plans', PlatformPlanController::class);
        Route::get('/country-defaults', PlatformCountryDefaultsController::class);
        Route::get('/companies', [PlatformCompanyController::class, 'index']);
        Route::post('/companies', [PlatformCompanyController::class, 'store']);
        Route::get('/companies/health', [PlatformCompanyHealthController::class, 'index']);
        Route::get('/companies/{company}/health', PlatformCompanyHealthController::class);
        // MULTI-PAYS (#1952) : réparation/choix du pays d'un tenant legacy
        // (refusé si données de paie — invariant 9).
        Route::patch('/companies/{company}/country', [PlatformCompanyController::class, 'updateCountry']);
        Route::get('/companies/{company}/subscription', [PlatformCompanySubscriptionController::class, 'show']);
        Route::patch('/companies/{company}/subscription', [PlatformCompanySubscriptionController::class, 'update']);
        Route::get('/companies/{company}/features', [PlatformCompanyFeatureController::class, 'show']);
        Route::patch('/companies/{company}/features', [PlatformCompanyFeatureController::class, 'update']);
        Route::get('/metrics/overview', PlatformMetricsOverviewController::class);

        // PA2-QA-006 — Redis/jobs observability (queue depth, failed jobs,
        // scheduled task last-run) for the super-admin "System" screen.
        Route::get('/observability/queues', QueueObservabilityController::class);

        // PA2-ADM-005 — Cross-tenant notification failure rate (24h) +
        // curated runbook links for the super-admin "System" screen.
        Route::get('/observability/notifications', PlatformNotificationObservabilityController::class);

        Route::get('/company-requests', [PlatformCompanyRequestController::class, 'index']);
        Route::get('/company-requests/{id}', [PlatformCompanyRequestController::class, 'show'])->whereNumber('id');
        Route::patch('/company-requests/{id}', [PlatformCompanyRequestController::class, 'updateStatus'])->whereNumber('id');

        Route::get('/crm/pipeline', PlatformCrmPipelineController::class);

        // PA2-COMM-012 — Pilot client support center: super-admin triage of
        // tenant-opened support tickets (status, priority, assignment, reply).
        Route::get('/support-tickets', [PlatformSupportTicketController::class, 'index']);
        Route::get('/support-tickets/{supportTicket}', [PlatformSupportTicketController::class, 'show'])->whereNumber('supportTicket');
        Route::post('/support-tickets/{supportTicket}/reply', [PlatformSupportTicketController::class, 'reply'])->whereNumber('supportTicket');
        Route::patch('/support-tickets/{supportTicket}/triage', [PlatformSupportTicketController::class, 'triage'])->whereNumber('supportTicket');

        // PA2-COMM-005 — Platform-wide announcements (maintenance, feature,
        // incident, action required) broadcast by super-admin to all or a
        // selected subset of companies.
        Route::get('/announcements', [PlatformAnnouncementController::class, 'index']);
        Route::post('/announcements', [PlatformAnnouncementController::class, 'store']);
        Route::get('/announcements/{announcement}', [PlatformAnnouncementController::class, 'show']);
        Route::delete('/announcements/{announcement}', [PlatformAnnouncementController::class, 'destroy']);

        // QA wave 2026-08-14 — T004 (#2229) : CRUD utilisateurs plateforme.
        Route::get('/users', [PlatformUserController::class, 'index']);
        Route::post('/users', [PlatformUserController::class, 'store']);
        Route::get('/users/{user}', [PlatformUserController::class, 'show'])->whereNumber('user');
        Route::patch('/users/{user}', [PlatformUserController::class, 'update'])->whereNumber('user');
        Route::delete('/users/{user}', [PlatformUserController::class, 'destroy'])->whereNumber('user');
        Route::post('/users/{user}/activate', [PlatformUserController::class, 'activate'])->whereNumber('user');
        Route::post('/users/{user}/deactivate', [PlatformUserController::class, 'deactivate'])->whereNumber('user');
        Route::post('/users/{user}/suspend', [PlatformUserController::class, 'suspend'])->whereNumber('user');

        // PA2-ADM-006 — Secure super-admin impersonation ("log in as this
        // employee"): mandatory reason, hard time limit, fully audited.
        Route::get('/impersonations', [PlatformImpersonationController::class, 'index']);
        Route::post('/impersonations', [PlatformImpersonationController::class, 'store']);
        Route::delete('/impersonations/{session}', [PlatformImpersonationController::class, 'destroy'])->whereNumber('session');

        // Edge node management (super-admin).
        // Uses EdgeNodeController against the canonical UUID edge_nodes
        // schema (see issue #1291) — the legacy bigint-schema EdgeController
        // equivalents were removed because that schema is never created.
        Route::prefix('edge/nodes')->group(function (): void {
            Route::get('/', [EdgeNodeController::class, 'listAllNodes']);
            Route::post('/{nodeId}/sync', [EdgeNodeController::class, 'forceSync']);
            Route::delete('/{nodeId}', [EdgeNodeController::class, 'revokeNode']);
        });
    });
    // Admin cockpit (super-admin) — contrat SPA front/admin-dashboard.
    // Endpoints appelés par le SPA sans exister côté API (issue #1764) :
    // création côté API des routes manquantes (décision produit).
    Route::middleware(['auth:super_admin_api', 'throttle:platform-sensitive'])->prefix('admin')->group(function (): void {
        // Issue #2624 : impersonation super-admin aussi sous /admin (le SPA
        // admin-dashboard consomme /admin/*) — réutilise le contrôleur
        // platform existant (PA2-ADM-006).
        Route::get('/impersonations', [PlatformImpersonationController::class, 'index']);
        Route::post('/impersonations', [PlatformImpersonationController::class, 'store']);
        Route::delete('/impersonations/{session}', [PlatformImpersonationController::class, 'destroy'])->whereNumber('session');

        Route::get('/dashboard/stats', [PlatformAdminDashboardController::class, 'stats']);
        Route::get('/dashboard/activities', [PlatformAdminDashboardController::class, 'activities']);
        Route::get('/dashboard/alerts', [PlatformAdminDashboardController::class, 'alerts']);
        Route::post('/dashboard/alerts/{alertKey}/dismiss', [PlatformAdminDashboardController::class, 'dismissAlert'])
            ->where('alertKey', '[A-Za-z0-9\-_]+');

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

        Route::get('/platform/marketing/oauth-config', [PlatformMarketingOAuthConfigController::class, 'index']);
        Route::put('/platform/marketing/oauth-config', [PlatformMarketingOAuthConfigController::class, 'update']);

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
