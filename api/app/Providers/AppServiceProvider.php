<?php

namespace App\Providers;

use App\AI\LLMClient;
use App\AI\Providers\ClaudeClient;
use App\AI\Providers\OpenAIClient;
use App\Services\TenantManager;
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
    }
}
