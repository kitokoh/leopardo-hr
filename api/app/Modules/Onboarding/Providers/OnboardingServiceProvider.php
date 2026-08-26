<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Providers;

use App\Modules\HR\Domain\Contracts\OnboardingQrInterface;
use App\Modules\Onboarding\Infrastructure\Services\OnboardingQrService;
use Illuminate\Support\ServiceProvider;

class OnboardingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // PA2-ARCH-003 — chaque module enregistre SA propre implémentation des
        // contrats qu'il fournit (composition root décentralisée).
        $this->app->bind(OnboardingQrInterface::class, OnboardingQrService::class);
    }

    public function boot(): void {}
}
