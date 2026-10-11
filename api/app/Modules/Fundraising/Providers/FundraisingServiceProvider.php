<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Providers;

use App\Modules\Fundraising\Infrastructure\Services\FundraisingGatewayFactory;
use Illuminate\Support\ServiceProvider;

/**
 * Provider du module Fundraising (verticale cagnottes solidaires — spec
 * docs/specifications/SOLUTION_FUNDRAISING.md).
 *
 * Bindings : factory des passerelles de paiement (config/fundraising.php).
 * Les Policies métier sont enregistrées centralement dans
 * App\Providers\AuthServiceProvider (règle PA2-ARCH-008) ; le gate
 * `module.fundraising` est aliasé dans bootstrap/app.php ; le feature flag
 * `fundraising` est déclaré dans config/feature-flags.php +
 * Company::KNOWN_MODULES (3 points d'enregistrement obligatoires).
 */
class FundraisingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FundraisingGatewayFactory::class, fn (): FundraisingGatewayFactory => new FundraisingGatewayFactory(
            is_array(config('fundraising')) ? config('fundraising') : []
        ));
    }

    public function boot(): void
    {
        // Les Policies métier sont enregistrées centralement dans
        // App\Providers\AuthServiceProvider (règle PA2-ARCH-008).
    }
}
