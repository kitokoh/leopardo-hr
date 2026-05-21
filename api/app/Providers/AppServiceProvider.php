<?php

namespace App\Providers;

use App\AI\LLMClient;
use App\AI\Providers\ClaudeClient;
use App\AI\Providers\OpenAIClient;
use App\Models\Applicant;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\FeaturePlanMatrix;
use App\Models\Invoice;
use App\Models\JobPosting;
use App\Models\OnboardingStep;
use App\Models\PayrollRun;
use App\Models\PaySlip;
use App\Models\Subscription;
use App\Models\TrainingCourse;
use App\Models\Vehicle;
use App\Policies\AttendancePolicy;
use App\Policies\BillingPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\EvaluationPolicy;
use App\Policies\ExportPolicy;
use App\Policies\FeatureFlagPolicy;
use App\Policies\OnboardingPolicy;
use App\Policies\PayrollPolicy;
use App\Policies\RecruitmentPolicy;
use App\Policies\TrainingPolicy;
use App\Policies\VehiclePolicy;
use App\Services\TenantManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantManager::class);

        $this->app->bind(LLMClient::class, function (): LLMClient {
            $provider = (string) config('ai.provider', 'openai');

            return $provider === 'claude' ? new ClaudeClient : new OpenAIClient;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Applicant::class, RecruitmentPolicy::class);
        Gate::policy(AttendanceLog::class, AttendancePolicy::class);
        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(Evaluation::class, EvaluationPolicy::class);
        Gate::policy(FeaturePlanMatrix::class, FeatureFlagPolicy::class);
        Gate::policy(Invoice::class, BillingPolicy::class);
        Gate::policy(JobPosting::class, RecruitmentPolicy::class);
        Gate::policy(OnboardingStep::class, OnboardingPolicy::class);
        Gate::policy(PayrollRun::class, PayrollPolicy::class);
        Gate::policy(PaySlip::class, PayrollPolicy::class);
        Gate::policy(Subscription::class, BillingPolicy::class);
        Gate::policy(TrainingCourse::class, TrainingPolicy::class);
        Gate::policy(Vehicle::class, VehiclePolicy::class);
        Gate::define('export', [ExportPolicy::class, 'export']);
        Gate::define('viewExportHistory', [ExportPolicy::class, 'viewHistory']);
        Gate::define('downloadExport', [ExportPolicy::class, 'download']);

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
