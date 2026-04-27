<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\TenantManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(app()->isLocal());

        RateLimiter::for('api', function (Request $request) {
            // Exclure les healthchecks du rate limiting
            if ($request->is('api/v1/health')) {
                return Limit::none();
            }

            $employee = $request->user();
            if ($employee && $employee->company_id) {
                // 300 requêtes par minute par entreprise
                return Limit::perMinute(300)->by('company:' . $employee->company_id);
            }

            // 60 requêtes par minute par IP pour les non-authentifiés
            return Limit::perMinute(60)->by($request->ip());
        });
    }
}
