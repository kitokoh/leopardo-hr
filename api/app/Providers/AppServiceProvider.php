<?php

namespace App\Providers;

use App\AI\LLMClient;
use App\AI\Providers\ClaudeClient;
use App\AI\Providers\OpenAIClient;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\TenantManager;
use App\Policies\ExportPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
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
        // #1766 — MAIL_URL vide (clé présente dans .env.example) → normalisée à
        // null avant toute résolution du MailManager (même config pré-cachée).
        self::normalizeEmptyMailerUrls();

        // PA2-ARCH-008 : point d'enregistrement unique. Tous les Gate::policy(...)
        // vivent desormais exclusivement dans AuthServiceProvider::boot() ; ce
        // provider ne garde que les Gate::define(...) qui n'y sont pas dupliques.
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

        // PA2-API-005 — The kiosk web punch endpoint (public, device-code based,
        // no Sanctum auth) needs its own throttle bucket keyed by device code so a
        // single compromised/misbehaving kiosk cannot exhaust another kiosk's
        // quota, while still bounding brute-force attempts against a device code.
        RateLimiter::for('kiosk-punch', function (Request $request) {
            $deviceCode = strtoupper((string) $request->route('deviceCode', 'unknown'));

            return Limit::perMinute((int) config('security.rate_limits.kiosk_punch_per_minute', 30))
                ->by('kiosk-punch:'.$deviceCode.'|'.$request->ip());
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
            'business' => (int) config('security.plan_rate_limits.business_per_minute', 1000),
            'professional' => (int) config('security.plan_rate_limits.professional_per_minute', 1000),
            'pro' => (int) config('security.plan_rate_limits.pro_per_minute', 1000),
            'starter' => (int) config('security.plan_rate_limits.starter_per_minute', 100),
            'trial' => (int) config('security.plan_rate_limits.trial_per_minute', 60),
            default => (int) config('security.plan_rate_limits.default_per_minute', 100),
        };
    }

    /**
     * #1766 — Une MAIL_URL vide (clé présente dans .env.example) ne doit pas être
     * traitée comme un DSN par Illuminate\Mail\MailManager : isset($config['url'])
     * est vrai même pour '' et le DSN vide écrase `transport` par null
     * (« Unsupported mail transport [] »). On normalise à null au boot, y compris
     * quand la config a été pré-cachée (config:cache) avec l'ancienne valeur.
     */
    public static function normalizeEmptyMailerUrls(): void
    {
        foreach (config('mail.mailers', []) as $name => $settings) {
            if (is_array($settings) && array_key_exists('url', $settings) && $settings['url'] === '') {
                config()->set("mail.mailers.{$name}.url", null);
            }
        }
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

        return 'trial';
    }

    private function normalizePlan(string $plan): string
    {
        return strtolower(trim($plan));
    }
}
