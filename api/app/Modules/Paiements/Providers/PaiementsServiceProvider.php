<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Providers;

use App\Modules\Paiements\Infrastructure\Services\FeeRuleResolver;
use App\Modules\Paiements\Infrastructure\Services\LedgerService;
use App\Modules\Paiements\Infrastructure\Services\PaymentService;
use App\Modules\Paiements\Infrastructure\Services\ProviderResolver;
use App\Modules\Paiements\Infrastructure\Services\ProviderTypeRegistry;
use App\Modules\Paiements\Infrastructure\Services\ReconciliationService;
use Illuminate\Support\ServiceProvider;

/**
 * BC-38 PAIEMENTS — provider du module.
 *
 * Le registre des types est un singleton : il est construit une fois depuis le
 * code (`withDefaults()`), et c'est cette instance unique qui sert de source de
 * verite pour la liste des types affichee en lecture seule au Superadmin.
 */
class PaiementsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            ProviderTypeRegistry::class,
            static fn (): ProviderTypeRegistry => ProviderTypeRegistry::withDefaults(),
        );

        $this->app->singleton(LedgerService::class);
        $this->app->singleton(ProviderResolver::class);
        $this->app->singleton(FeeRuleResolver::class);
        $this->app->singleton(ReconciliationService::class);
        $this->app->singleton(PaymentService::class);
    }

    public function boot(): void
    {
        // Les Policies metier sont enregistrees centralement dans
        // App\Providers\AuthServiceProvider (regle PA2-ARCH-008).
    }
}
