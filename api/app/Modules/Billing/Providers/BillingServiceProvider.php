<?php

declare(strict_types=1);

namespace App\Modules\Billing\Providers;

use App\Modules\Billing\Infrastructure\Services\GatewaySettingsService;
use App\Shared\Contracts\Payments\PaymentGatewayConfigProviderInterface;
use Illuminate\Support\ServiceProvider;

class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // #7726 — configuration PSP plateforme : contrat PARTAGÉ (consommé
        // aussi par Accounting, garde d'isolation des modules #5584),
        // implémentation Billing (précédence BDD → fallback env).
        $this->app->singleton(PaymentGatewayConfigProviderInterface::class, GatewaySettingsService::class);
    }

    public function boot(): void {}
}
