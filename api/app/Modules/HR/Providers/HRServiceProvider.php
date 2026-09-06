<?php

declare(strict_types=1);

namespace App\Modules\HR\Providers;

use App\AI\Support\AIToolDefinitionRegistry;
use App\Modules\HR\Domain\Contracts\ApplicantPipelineReaderInterface;
use App\Modules\HR\Domain\Support\HrReadToolCatalog;
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
        // Garde d'idempotence (#6947) : AIToolDefinitionRegistry est un
        // collecteur STATIQUE (process-wide) — le boot des providers est
        // rejoué à chaque requête (PHP-FPM) et à chaque test (PHPUnit) ; sans
        // cette garde, la 2e exécution du process lève « AIToolDefinition
        // dupliquée » (InvalidArgumentException) pendant le boot.
        foreach (HrReadToolCatalog::definitions() as $definition) {
            if (! AIToolDefinitionRegistry::has($definition->name)) {
                AIToolDefinitionRegistry::register($definition);
            }
        }
    }
}
