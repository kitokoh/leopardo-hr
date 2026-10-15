<?php

declare(strict_types=1);

namespace App\Modules\Planning\Providers;

use App\AI\Support\AIToolDefinitionRegistry;
use App\Modules\Planning\Domain\Support\ShiftAssignToolCatalog;
use Illuminate\Support\ServiceProvider;

class PlanningServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Aucun binding : les Actions canoniques sont résolues par le
        // container (autowiring sur les services Infrastructure).
    }

    public function boot(): void
    {
        // B3b (#6857) — déclaration de l'outil écriture `shift_assign` au
        // contrat A3 (BC-23, #6850) : l'hôte ToolRegistry enrichit l'entrée
        // ai_tool_registry homonyme au boot. Garde d'idempotence (#6947) :
        // AIToolDefinitionRegistry est un collecteur statique re-booté à
        // chaque requête (PHP-FPM) et à chaque test.
        foreach (ShiftAssignToolCatalog::definitions() as $definition) {
            if (! AIToolDefinitionRegistry::has($definition->name)) {
                AIToolDefinitionRegistry::register($definition);
            }
        }
    }
}
