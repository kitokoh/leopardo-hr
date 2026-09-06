<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Providers;

use App\AI\Support\AIToolDefinitionRegistry;
use App\Core\Auth\Domain\Models\AuditLog;
use App\Modules\Payroll\Domain\Support\PayrollReadToolCatalog;
use App\Modules\Payroll\Infrastructure\Listeners\PayrollAccountingEntryObserver;
use Illuminate\Support\ServiceProvider;

class PayrollServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Issue #5239 — écritures salariales automatiques : à la validation RH
        // d'un run (`AuditLog` action payroll_run_validated), générer les
        // écritures comptables via PayrollAccountingEntryService.
        AuditLog::observe(PayrollAccountingEntryObserver::class);

        // B2 (#6855) — déclaration des outils lecture Payroll au contrat A3
        // (BC-23, #6850) : l'hôte ToolRegistry enrichit l'entrée
        // ai_tool_registry homonyme (sensibilité read, BC propriétaire,
        // schémas) au boot.
        foreach (PayrollReadToolCatalog::definitions() as $definition) {
            AIToolDefinitionRegistry::register($definition);
        }
    }
}
