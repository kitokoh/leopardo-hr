<?php

declare(strict_types=1);

namespace App\Modules\HR\Providers;

use App\AI\Support\AIToolDefinitionRegistry;
use App\Modules\HR\Domain\Contracts\ApplicantPipelineReaderInterface;
use App\Modules\HR\Domain\Contracts\ContractDocumentGeneratorInterface;
use App\Modules\HR\Domain\Support\HrReadToolCatalog;
use App\Modules\HR\Infrastructure\Services\ContractPdfGenerator;
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

        // B1 (#6854) — déclaration des outils lecture HR au contrat A3 (BC-23,
        // #6850) : l'hôte ToolRegistry enrichit les entrées ai_tool_registry
        // homonymes (sensibilité read, BC propriétaire, schémas) au boot.
        foreach (HrReadToolCatalog::definitions() as $definition) {
            AIToolDefinitionRegistry::register($definition);
        }
    }
}
