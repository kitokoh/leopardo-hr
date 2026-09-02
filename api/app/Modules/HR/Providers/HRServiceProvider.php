<?php

declare(strict_types=1);

namespace App\Modules\HR\Providers;

use App\Modules\HR\Domain\Contracts\ApplicantPipelineReaderInterface;
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
    }

    public function boot(): void
    {
        // Issue #5261 — embauche candidat (fichier dédié, rh.php/hr_extended verrouillés)
        $this->loadRoutesFrom(__DIR__.'/../routes/candidate_hiring.php');
    }
}
