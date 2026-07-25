<?php

declare(strict_types=1);

namespace App\Modules\HR\Providers;

use App\Modules\Cabinet\Infrastructure\Services\ContractPdfGenerator;
use App\Modules\HR\Domain\Contracts\ApplicantPipelineReaderInterface;
use App\Modules\HR\Domain\Contracts\ContractDocumentGeneratorInterface;
use App\Modules\HR\Domain\Contracts\OnboardingQrInterface;
use App\Modules\Onboarding\Infrastructure\Services\OnboardingQrService;
use App\Modules\Recruitment\Infrastructure\Services\ApplicantPipelineReader;
use Illuminate\Support\ServiceProvider;

class HRServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // PA2-ARCH-003: HR depends on these interfaces rather than
        // importing the Recruitment/Cabinet/Onboarding concrete classes
        // directly in its controllers. Bindings below wire the existing
        // implementations from those modules (reused, not duplicated).
        $this->app->bind(ApplicantPipelineReaderInterface::class, ApplicantPipelineReader::class);
        $this->app->bind(ContractDocumentGeneratorInterface::class, ContractPdfGenerator::class);
        $this->app->bind(OnboardingQrInterface::class, OnboardingQrService::class);
    }

    public function boot(): void
    {
        // Boot HR module
    }
}
