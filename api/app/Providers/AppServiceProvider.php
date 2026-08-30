<?php

namespace App\Providers;

use App\AI\LLMClient;
use App\AI\Providers\ClaudeClient;
use App\AI\Providers\OpenAIClient;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\TenantManager;
use App\Modules\Billing\Domain\Enums\PlanCode;
use App\Modules\Payroll\Infrastructure\Services\IslamicCalendarService;
use App\Modules\Payroll\Infrastructure\Services\PublicHolidayService;
use App\Policies\ExportPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Canonical singleton — tous les nouveaux usages
        $this->app->singleton(TenantManager::class);

        // Issue #1811 : service jours fériés — cache Redis (repository par défaut).
        // Issue #1812 : le calendrier islamique (dates mobiles saisies par
        // l'admin) est fusionné dans le calcul des jours ouvrés.
        $this->app->singleton(IslamicCalendarService::class, fn (): IslamicCalendarService => new IslamicCalendarService(Cache::store()));
        $this->app->singleton(
            PublicHolidayService::class,
            fn (): PublicHolidayService => new PublicHolidayService(Cache::store(), app(IslamicCalendarService::class)),
        );

        $this->app->bind(LLMClient::class, function (): LLMClient {
            $provider = (string) config('ai.provider', 'openai');

            return $provider === 'claude' ? new ClaudeClient : new OpenAIClient;
        });

        // Resolution des factories pour les modeles deplaces en DDD (Core/Modules/*).
        // Toutes les factories vivent a plat dans database/factories/{Model}Factory.php ;
        // le guesser par defaut de Laravel calcule le namespace complet du modele
        // (ex: Database\Factories\Core\Tenant\Domain\Models\CompanyFactory) qui n'existe
        // pas. On ne garde que le nom court de la classe.
        Factory::guessFactoryNamesUsing(function (string $modelName): string {
            $shortName = Str::afterLast($modelName, '\\');

            return 'Database\\Factories\\'.$shortName.'Factory';
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // PA2-ARCH-008 : point d'enregistrement unique. Tous les Gate::policy(...)
        // vivent exclusivement dans AuthServiceProvider::boot() (y compris
        // WebhookEndpoint) ; ce provider ne garde que les Gate::define(...).
        Gate::define('export', [ExportPolicy::class, 'export']);
        Gate::define('viewExportHistory', [ExportPolicy::class, 'viewHistory']);
        Gate::define('downloadExport', [ExportPolicy::class, 'download']);

        Gate::define('viewApiDocs', function (?Employee $user = null) {
            // Pour l'instant, on autorise l'accès à la doc en dev, ou on peut exiger un accès Super Admin
            return app()->environment('local') || ($user && $user->company_id);
        });

        Model::preventLazyLoading(app()->isLocal());

        RateLimiter::for('api', function (Request $request) {
            // Exclure les healthchecks du rate limiting
            if ($request->is('api/v1/health')) {
                return Limit::none();
            }

            $employee = $request->user();
            if ($employee && $employee->company_id) {
                // 300 requêtes par minute par entreprise
                return Limit::perMinute(300)->by('company:'.$employee->company_id);
            }

            // 60 requêtes par minute par IP pour les non-authentifiés
            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('api-plan', function (Request $request) {
            $user = $request->user();
            if (! $user instanceof Employee || ! $user->company_id) {
                return Limit::perMinute((int) config('security.plan_rate_limits.default_per_minute', 100))
                    ->by('plan:ip:'.$request->ip());
            }

            $plan = $this->resolveCompanyPlan((string) $user->company_id);
            $limit = $this->resolvePlanLimit($plan);
            $normalizedPlan = $this->normalizePlan($plan);

            if ($limit <= 0) {
                return Limit::none();
            }

            return Limit::perMinute($limit)->by('plan:'.$normalizedPlan.':company:'.$user->company_id);
        });

        RateLimiter::for('auth-sensitive', function (Request $request) {
            $email = strtolower((string) $request->input('email', 'anonymous'));

            return Limit::perMinute((int) config('security.rate_limits.auth_per_minute', 10))
                ->by('auth:'.$email.'|'.$request->ip());
        });

        RateLimiter::for('privacy-sensitive', function (Request $request) {
            $user = $request->user();
            $key = $user instanceof Employee
                ? 'employee:'.$user->company_id.':'.$user->id
                : 'ip:'.$request->ip();

            return Limit::perMinute((int) config('security.rate_limits.privacy_per_minute', 20))
                ->by('privacy:'.$key);
        });

        RateLimiter::for('payroll-sensitive', function (Request $request) {
            $user = $request->user();
            $key = $user instanceof Employee && $user->company_id
                ? 'company:'.$user->company_id
                : 'ip:'.$request->ip();

            return Limit::perMinute((int) config('security.rate_limits.payroll_per_minute', 60))
                ->by('payroll:'.$key);
        });

        RateLimiter::for('metrics', function (Request $request) {
            // #4694 : les métriques plateforme sont du matériel de
            // fingerprinting (versions PHP/Laravel, drivers, compteurs
            // tenants/employés) — même authentifié, on borne la fréquence
            // d'interrogation (anti-scraping, anti-recon).
            $user = $request->user('super_admin_api');
            $key = $user instanceof AuthenticatableContract
                ? $user->getAuthIdentifier()
                : $request->ip();

            return Limit::perMinute((int) config('security.rate_limits.metrics_per_minute', 30))
                ->by('metrics:'.$key);
        });

        RateLimiter::for('platform-sensitive', function (Request $request) {
            $user = $request->user('super_admin_api');
            $userId = $user instanceof AuthenticatableContract ? $user->getAuthIdentifier() : null;
            $key = $userId !== null ? 'super-admin:'.$userId : 'ip:'.$request->ip();

            return Limit::perMinute((int) config('security.rate_limits.platform_per_minute', 60))
                ->by('platform:'.$key);
        });

        RateLimiter::for('ai-sensitive', function (Request $request) {
            $user = $request->user();
            $key = $user instanceof Employee && $user->company_id
                ? 'company:'.$user->company_id
                : 'ip:'.$request->ip();

            return Limit::perMinute((int) config('security.rate_limits.ai_per_minute', 20))
                ->by('ai:'.$key);
        });

        RateLimiter::for('client-analytics', function (Request $request) {
            $user = $request->user();
            $key = $user instanceof Employee && $user->company_id
                ? 'company:'.$user->company_id
                : 'ip:'.$request->ip();

            return Limit::perMinute((int) config('security.rate_limits.client_analytics_per_minute', 120))
                ->by('client-analytics:'.$key);
        });

        // PA2-API-005 — Inbound partner/provider webhooks (Stripe, Chargily) are
        // public and unauthenticated by nature (verified by signature inside the
        // controller, not by Sanctum), so they need their own throttle bucket
        // instead of relying on the generic 'api' limiter which only applies to
        // authenticated routes further down the group. Keyed by IP since the
        // caller is a third-party payment provider, not a tenant.
        RateLimiter::for('webhooks-inbound', function (Request $request) {
            return Limit::perMinute((int) config('security.rate_limits.webhooks_inbound_per_minute', 60))
                ->by('webhooks-inbound:'.$request->ip());
        });

        // Audit expert 2026-08-15 (issue #2621) — GET /trial/status est pollé
        // par l'UI vitrine toutes les 5 s (jusqu'à ~12 requêtes/min) ; il doit
        // avoir son propre bucket, distinct de `5,15` réservé aux mutations
        // (signup/verify). Clé par IP + token pour limiter le scraping.
        RateLimiter::for('trial-status', function (Request $request) {
            $token = strtolower((string) $request->query('token', 'anonymous'));

            return Limit::perMinute(60)->by('trial-status:'.$token.'|'.$request->ip());
        });

        // Issue #4217 (audit 360° 2026-08-16) — GET /supported-countries devient
        // public (registre multi-pays canonique #1867, aucune PII) : bucket
        // dédié 60/min par IP pour la vitrine/onboarding/mobile pré-login.
        RateLimiter::for('public-registry', function (Request $request) {
            return Limit::perMinute(60)->by('public-registry:'.$request->ip());
        });

        // RESTO-805 (#6226) — boutique publique RestaurantManager : bucket
        // renforcé (défaut 30/min par IP) pour le menu public / commande en
        // ligne / kiosque (pattern TRAVEL-1001/#6114, `shop-public`).
        RateLimiter::for('shop-public', function (Request $request) {
            return Limit::perMinute((int) config('security.rate_limits.shop_public_per_minute', 30))
                ->by('shop-public:'.$request->ip());
        });

        // PA2-API-005 — Session-based web login forms (employee login, super-admin
        // platform login) are not covered by the API 'auth-sensitive' limiter
        // above, which only guards the Sanctum token endpoints. Keyed by e-mail +
        // IP, mirroring 'auth-sensitive', to slow down brute-force credential
        // stuffing without needing a Sanctum token.
        RateLimiter::for('web-login', function (Request $request) {
            $email = strtolower((string) $request->input('email', 'anonymous'));

            return Limit::perMinute((int) config('security.rate_limits.web_login_per_minute', 10))
                ->by('web-login:'.$email.'|'.$request->ip());
        });

        // #4498 — invitation activation (public, token-based, sets the employee
        // password) : same brute-force exposure as the API twin
        // /onboarding/invitation/{token} (throttled 10/min). Dedicated bucket
        // keyed by token + IP.
        RateLimiter::for('web-activate', function (Request $request) {
            $token = (string) $request->route('token', 'unknown');

            return Limit::perMinute((int) config('security.rate_limits.web_activate_per_minute', 10))
                ->by('web-activate:'.$token.'|'.$request->ip());
        });

        // PA2-API-005 — The kiosk web punch endpoint (public, device-code based,
        // no Sanctum auth) needs its own throttle bucket keyed by device code so a
        // single compromised/misbehaving kiosk cannot exhaust another kiosk's
        // quota, while still bounding brute-force attempts against a device code.
        RateLimiter::for('kiosk-punch', function (Request $request) {
            $deviceCode = strtoupper((string) $request->route('deviceCode', 'unknown'));

            return Limit::perMinute((int) config('security.rate_limits.kiosk_punch_per_minute', 30))
                ->by('kiosk-punch:'.$deviceCode.'|'.$request->ip());
        });

        // #4607 : GET de la page kiosk (device_code = seule credential) —
        // bucket DÉDIÉ (120/min) pour ne pas partager le quota d'écriture
        // kiosk-punch (30/min) : une borne active qui recharge la page à
        // chaque pointage consommerait GET+PUNCH dans le même bucket → 429
        // sur le flux légitime. 120/min borne quand même l'énumération.
        RateLimiter::for('kiosk-show', function (Request $request) {
            $deviceCode = strtoupper((string) $request->route('deviceCode', 'unknown'));

            return Limit::perMinute((int) config('security.rate_limits.kiosk_show_per_minute', 120))
                ->by('kiosk-show:'.$deviceCode.'|'.$request->ip());
        });

        // Public careers portal (job listing/detail/feed + candidate
        // applications) has no Sanctum guard, so it needs its own IP-keyed
        // throttle bucket rather than relying on the authenticated 'api' one.
        RateLimiter::for('public-careers', function (Request $request) {
            return Limit::perMinute((int) config('security.rate_limits.public_careers_per_minute', 60))
                ->by('public-careers:'.$request->ip());
        });

    }

    private function resolvePlanLimit(string $plan): int
    {
        $normalized = $this->normalizePlan($plan);

        return match ($normalized) {
            'enterprise' => (int) config('security.plan_rate_limits.enterprise_per_minute', 0),
            'operations' => (int) config('security.plan_rate_limits.operations_per_minute', 1000),
            'pilot' => (int) config('security.plan_rate_limits.pilot_per_minute', 100),
            'free' => (int) config('security.plan_rate_limits.free_per_minute', 60),
            default => (int) config('security.plan_rate_limits.default_per_minute', 100),
        };
    }

    private function resolveCompanyPlan(string $companyId): string
    {
        $planName = DB::table('companies')
            ->leftJoin('plans', 'plans.id', '=', 'companies.plan_id')
            ->where('companies.id', $companyId)
            ->value('plans.name');

        if (is_string($planName) && $planName !== '') {
            return $planName;
        }

        return PlanCode::Free->value;
    }

    private function normalizePlan(string $plan): string
    {
        try {
            return PlanCode::normalize($plan)->value;
        } catch (\InvalidArgumentException) {
            return strtolower(trim($plan));
        }
    }
}
