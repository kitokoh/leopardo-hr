<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Providers;

use App\AI\Support\AIToolDefinitionRegistry;
use App\Core\Auth\Domain\Models\AuditLog;
use App\Modules\Payroll\Domain\Support\PayrollReadToolCatalog;
use App\Modules\Payroll\Infrastructure\Listeners\PayrollAccountingEntryObserver;
use App\Modules\Payroll\Infrastructure\Services\SharedPublicHolidayCalendar;
use App\Shared\Contracts\Payroll\PublicHolidayCalendar;
use Illuminate\Support\ServiceProvider;

class PayrollServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // BOS-023 (#8211) — contrat partagé « jours fériés » : les consommateurs
        // (Planning, BC-05) ne dépendent plus du module Payroll (#5584).
        $this->app->bind(PublicHolidayCalendar::class, SharedPublicHolidayCalendar::class);
    }

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
        // Garde d'idempotence (#6947) : le registre est un collecteur
        // statique qui survit aux re-boots applicatifs (PHPUnit : un
        // process = N boots) → sans la garde, le 2e boot lève
        // « AIToolDefinition dupliquée » et fait tomber la suite Feature
        // entière (même pattern que HR/Absence/Notification).
        foreach (PayrollReadToolCatalog::definitions() as $definition) {
            if (! AIToolDefinitionRegistry::has($definition->name)) {
                AIToolDefinitionRegistry::register($definition);
            }
        }
    }
}
