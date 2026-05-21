<?php

use App\Http\Controllers\Api\FeatureManifestController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BiometricEnrollmentController;
use App\Http\Controllers\Api\V1\CompanyRequestController;
use App\Http\Controllers\Api\V1\DemoUserController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MetricsController;
use App\Http\Controllers\Api\V1\OnboardingChecklistController;
use App\Http\Controllers\Api\V1\OnboardingController;
use App\Http\Controllers\Api\V1\PlatformAuthController;
use App\Http\Controllers\Api\V1\PlatformCompanyFeatureController;
use App\Http\Controllers\Api\V1\PlatformCompanyHealthController;
use App\Http\Controllers\Api\V1\PlatformCompanyRequestController;
use App\Http\Controllers\Api\V1\PlatformCompanySubscriptionController;
use App\Http\Controllers\Api\V1\PlatformMetricsOverviewController;
use App\Http\Controllers\Api\V1\PlatformPlanController;
use App\Http\Controllers\Api\V1\PrivacyController;
use App\Http\Controllers\Api\V1\TranslationCatalogController;
use App\Http\Controllers\Web\PlatformCompanyController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    // Sonde live+ready : DB + Redis + storage. Consommee par Render (deploy hook)
    // et la supervision externe. 503 si la DB tombe, 200 sinon (Redis et storage
    // peuvent etre degrades sans bloquer l'API).
    Route::get('/health', HealthController::class);
    Route::get('/health/live', [HealthController::class, 'live']);
    Route::get('/health/ready', [HealthController::class, 'ready']);
    Route::get('/metrics', MetricsController::class);

    // Auth (core, hors module)
    Route::middleware(['throttle:auth-sensitive'])->group(function (): void {
        Route::post('/auth/login', [AuthController::class, 'login']);
        Route::post('/auth/register', [AuthController::class, 'register']);
        Route::get('/auth/google', [AuthController::class, 'redirectToGoogle']);
        Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);
        Route::post('/auth/google/token', [AuthController::class, 'handleGoogleToken']);
        Route::post('/platform/auth/login', [PlatformAuthController::class, 'login']);
        Route::get('/i18n/catalog', [TranslationCatalogController::class, 'index']);
        Route::get('/i18n/catalog/{locale}', [TranslationCatalogController::class, 'show']);
    });

    // Demo users (public, disabled in production unless DEMO_MODE_ENABLED=true)
    Route::get('/demo-users', [DemoUserController::class, 'index']);

    // Module 6 — Public Onboarding (sans auth, throttle strict)
    Route::middleware(['throttle:10,1'])->prefix('onboarding')->group(function (): void {
        Route::get('/invitation/{token}', [OnboardingController::class, 'show']);
        Route::post('/invitation/{token}/activate', [OnboardingController::class, 'activate']);
    });

    Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan'])->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::patch('/auth/profile', [AuthController::class, 'updateProfile']);
        Route::patch('/auth/language', [AuthController::class, 'updateLanguage']);
        Route::post('/auth/change-password', [AuthController::class, 'changePassword']);
        Route::post('/auth/refresh-token', [AuthController::class, 'refreshToken']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
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
    require __DIR__.'/modules/user.php';
    require __DIR__.'/modules/tracking.php';
    require __DIR__.'/modules/dashboard.php';
    require __DIR__.'/modules/planning.php';

    require __DIR__.'/modules/billing.php';
    require __DIR__.'/modules/sso.php';
    require __DIR__.'/modules/integrations.php';

    // IA Module — routes separees /api/ai/*
    require __DIR__.'/ai.php';

    // Platform (super-admin, hors module)
    Route::middleware(['auth:super_admin_api', 'throttle:platform-sensitive'])->prefix('platform')->group(function (): void {
        Route::get('/auth/me', [PlatformAuthController::class, 'me']);
        Route::post('/auth/logout', [PlatformAuthController::class, 'logout']);

        // 2FA Super-Admin
        Route::post('/auth/2fa/setup', [PlatformAuthController::class, 'setup2fa']);
        Route::post('/auth/2fa/enable', [PlatformAuthController::class, 'enable2fa']);
        Route::post('/auth/2fa/disable', [PlatformAuthController::class, 'disable2fa']);
        Route::get('/plans', PlatformPlanController::class);
        Route::get('/companies', [PlatformCompanyController::class, 'index']);
        Route::post('/companies', [PlatformCompanyController::class, 'store']);
        Route::get('/companies/health', [PlatformCompanyHealthController::class, 'index']);
        Route::get('/companies/{company}/health', PlatformCompanyHealthController::class);
        Route::get('/companies/{company}/subscription', [PlatformCompanySubscriptionController::class, 'show']);
        Route::patch('/companies/{company}/subscription', [PlatformCompanySubscriptionController::class, 'update']);
        Route::get('/companies/{company}/features', [PlatformCompanyFeatureController::class, 'show']);
        Route::patch('/companies/{company}/features', [PlatformCompanyFeatureController::class, 'update']);
        Route::get('/metrics/overview', PlatformMetricsOverviewController::class);

        Route::get('/company-requests', [PlatformCompanyRequestController::class, 'index']);
        Route::get('/company-requests/{id}', [PlatformCompanyRequestController::class, 'show'])->whereNumber('id');
        Route::patch('/company-requests/{id}', [PlatformCompanyRequestController::class, 'updateStatus'])->whereNumber('id');
    });
});
